<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\BookingFeeRecorder;
use App\Domain\Fees\FeeBasis;
use App\Domain\Tickets\PassFactory;
use App\Domain\Tickets\TicketPass;
use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\QuestionField;
use App\Enums\TicketVisibility;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventSession;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Completes a registration only when every selected ticket is free.
 * Paid prices are stored on the ticket type and refused here.
 */
final class FreeCheckout
{
    public function __construct(
        private readonly InventoryBook $inventory,
        private readonly PassFactory $passes,
        private readonly BookingFeeRecorder $fees,
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @param  array<string, string>  $answers
     * @param  list<string>  $holdTokens
     * @return array{order: Order, passes: list<TicketPass>}
     */
    public function place(
        Event $event,
        array $lines,
        array $answers,
        string $guestName,
        string $guestEmail,
        ?string $buyerUserId,
        array $holdTokens = [],
    ): array {
        $this->assertContext($event);

        if ($lines === []) {
            throw new CheckoutRejected('Choose at least one ticket.');
        }

        /** @var list<TicketPass> $issued */
        $issued = [];

        $order = DB::transaction(function () use ($event, $lines, $answers, $guestName, $guestEmail, $buyerUserId, $holdTokens, &$issued): Order {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $lockedEvent instanceof Event) {
                throw new CheckoutRejected('This event is not available.', 404);
            }

            $prepared = $this->prepareLines($lockedEvent, $lines);
            $answerRows = $this->prepareAnswers($lockedEvent, $answers);
            $reservations = $this->holdsFor($prepared, $holdTokens);

            foreach ($prepared as $item) {
                if (! isset($reservations[$item['type']->id])) {
                    $hold = $this->inventory->reserve($item['type'], $item['quantity']);
                    $reservations[$item['type']->id] = $hold->reservation;
                }
            }

            $subtotal = 0;

            foreach ($prepared as $item) {
                $subtotal += $item['type']->price_cents * $item['quantity'];
            }

            $order = Order::query()->create([
                'organization_id' => $lockedEvent->organization_id,
                'buyer_user_id' => $buyerUserId,
                'guest_name' => $guestName,
                'guest_email' => strtolower($guestEmail),
                'status' => OrderStatus::Completed,
                'channel' => 'online',
                'currency' => 'USD',
                'ticket_subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $subtotal,
                'reference' => $this->reference(),
                'completed_at' => now(),
            ]);

            foreach ($prepared as $item) {
                $type = $item['type'];
                $sessionName = null;

                if ($type->session_id !== null) {
                    $sessionName = EventSession::query()->whereKey($type->session_id)->value('name');
                    $sessionName = is_string($sessionName) ? $sessionName : null;
                }

                $line = OrderLine::query()->create([
                    'organization_id' => $lockedEvent->organization_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $type->id,
                    'session_id' => $type->session_id,
                    'name_snapshot' => $type->name,
                    'kind_snapshot' => $type->kind->value,
                    'session_name_snapshot' => $sessionName,
                    'unit_price_cents' => $type->price_cents,
                    'quantity' => $item['quantity'],
                    'admits_snapshot' => $type->admits,
                    'line_total_cents' => $type->price_cents * $item['quantity'],
                ]);

                foreach ($item['attendees'] as $person) {
                    $attendee = Attendee::query()->create([
                        'organization_id' => $lockedEvent->organization_id,
                        'order_id' => $order->id,
                        'order_line_id' => $line->id,
                        'event_id' => $lockedEvent->id,
                        'ticket_type_id' => $type->id,
                        'name' => $person['name'],
                        'email' => strtolower($person['email']),
                    ]);
                    $issued[] = $this->passes->issue($attendee, $lockedEvent, $order->reference);
                }
            }

            foreach ($answerRows as $row) {
                $order->answers()->create([
                    'organization_id' => $lockedEvent->organization_id,
                    'event_question_id' => $row['id'],
                    'value' => $row['value'],
                ]);
            }

            foreach ($reservations as $reservation) {
                $this->inventory->consume($reservation, $order->id);
            }

            $snapshot = $this->fees->record(
                $lockedEvent->organization_id,
                $order->id,
                new FeeBasis($subtotal),
                now(),
            );

            $order->forceFill([
                'fee_cents' => $snapshot->fee_cents,
                'total_cents' => $subtotal + $snapshot->fee_cents,
            ])->save();

            $this->audit->write('order.completed', $lockedEvent->organization_id, $order, [
                'reference' => $order->reference,
                'total_cents' => $subtotal + $snapshot->fee_cents,
                'tickets' => count($issued),
            ]);

            return $order;
        });

        return ['order' => $order, 'passes' => $issued];
    }

    /**
     * @return list<TicketPass>
     */
    public function reissue(Order $order): array
    {
        $this->assertContext($this->eventFor($order));

        /** @var list<TicketPass> $issued */
        $issued = [];

        DB::transaction(function () use ($order, &$issued): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked instanceof Order || $locked->status !== OrderStatus::Completed) {
                throw new CheckoutRejected('This registration cannot be reissued.', 409);
            }

            Ticket::query()
                ->where('order_id', $locked->id)
                ->where('status', IssuedTicketStatus::Issued)
                ->update(['status' => IssuedTicketStatus::Void->value]);

            $attendees = Attendee::query()->where('order_id', $locked->id)->get();

            foreach ($attendees as $attendee) {
                $event = Event::query()->whereKey($attendee->event_id)->first();

                if (! $event instanceof Event) {
                    throw new CheckoutRejected('The event for this registration is missing.', 409);
                }

                $issued[] = $this->passes->issue($attendee, $event, $locked->reference);
            }

            $this->audit->write('order.reissued', $locked->organization_id, $locked, [
                'reference' => $locked->reference,
                'tickets' => count($issued),
            ]);
        });

        return $issued;
    }

    private function assertContext(Event $event): void
    {
        if ($this->organizations->id() !== $event->organization_id) {
            throw new CheckoutRejected('This event is not on the current site.', 403);
        }
    }

    private function eventFor(Order $order): Event
    {
        $eventId = Attendee::query()->where('order_id', $order->id)->value('event_id');
        $event = Event::query()->whereKey($eventId)->first();

        if (! $event instanceof Event) {
            throw new CheckoutRejected('This registration cannot be reissued.', 409);
        }

        return $event;
    }

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @return list<array{type: TicketType, quantity: int, attendees: list<array{name: string, email: string}>}>
     */
    private function prepareLines(Event $event, array $lines): array
    {
        $prepared = [];
        $seen = [];

        foreach ($lines as $line) {
            $typeId = $line['ticket_type_id'];

            if (isset($seen[$typeId])) {
                throw new CheckoutRejected('Each ticket type can appear once per registration.');
            }

            $seen[$typeId] = true;
            $type = TicketType::query()
                ->whereKey($typeId)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if (! $type instanceof TicketType) {
                throw new CheckoutRejected('That ticket is not part of this event.', 404);
            }

            $this->onSale($event, $type);
            $quantity = $line['quantity'];

            if ($quantity < $type->min_per_order || $quantity > $type->max_per_order) {
                throw new CheckoutRejected('That quantity is outside the limit for '.$type->name.'.');
            }

            if ($type->price_cents !== 0) {
                throw new CheckoutRejected('Paid tickets cannot be completed until the organizer connects a card processor.');
            }

            $people = $line['attendees'];

            if (count($people) !== $quantity) {
                throw new CheckoutRejected('Enter a name and email for each ticket.');
            }

            $attendees = [];

            foreach ($people as $person) {
                $name = trim($person['name']);
                $email = strtolower(trim($person['email']));

                if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckoutRejected('Enter a name and email for each ticket.');
                }

                $attendees[] = ['name' => $name, 'email' => $email];
            }

            $prepared[] = ['type' => $type, 'quantity' => $quantity, 'attendees' => $attendees];
        }

        return $prepared;
    }

    private function onSale(Event $event, TicketType $type): void
    {
        if ($event->status !== EventStatus::Published) {
            throw new CheckoutRejected('Tickets are not on sale for this event.', 409);
        }

        if ($type->visibility === TicketVisibility::Hidden) {
            throw new CheckoutRejected('That ticket is not available.', 404);
        }

        $now = now();

        foreach ([$event->sales_starts_at, $type->sales_starts_at] as $start) {
            if ($start !== null && $now->lt($start)) {
                throw new CheckoutRejected('Sales have not started.', 409);
            }
        }

        foreach ([$event->sales_ends_at, $type->sales_ends_at] as $end) {
            if ($end !== null && $now->gt($end)) {
                throw new CheckoutRejected('Sales have ended.', 409);
            }
        }
    }

    /**
     * @param  array<string, string>  $answers
     * @return list<array{id: string, value: string}>
     */
    private function prepareAnswers(Event $event, array $answers): array
    {
        $questions = EventQuestion::query()->where('event_id', $event->id)->get();
        $known = [];

        foreach ($questions as $question) {
            $known[$question->id] = $question;
        }

        foreach ($answers as $id => $value) {
            if (! isset($known[(string) $id])) {
                throw new CheckoutRejected('An answer does not match this event.');
            }
        }

        $rows = [];

        foreach ($questions as $question) {
            $value = trim((string) ($answers[$question->id] ?? ''));

            if ($question->field_type === QuestionField::Checkbox) {
                if ($value === '') {
                    $value = 'no';
                }

                if (! in_array($value, ['yes', 'no'], true)) {
                    throw new CheckoutRejected('Choose yes or no for '.$question->label.'.');
                }

                if ($question->required && $value !== 'yes') {
                    throw new CheckoutRejected($question->label.' is required.');
                }
            } elseif ($question->field_type === QuestionField::Select) {
                $options = array_values(array_map(strval(...), $question->options ?? []));

                if ($question->required && $value === '') {
                    throw new CheckoutRejected($question->label.' is required.');
                }

                if ($value !== '' && ! in_array($value, $options, true)) {
                    throw new CheckoutRejected('Choose a listed option for '.$question->label.'.');
                }
            } else {
                if ($question->required && $value === '') {
                    throw new CheckoutRejected($question->label.' is required.');
                }

                if (strlen($value) > 2000) {
                    throw new CheckoutRejected($question->label.' is too long.');
                }
            }

            if ($value !== '' && $value !== 'no') {
                $rows[] = ['id' => $question->id, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{type: TicketType, quantity: int, attendees: list<array{name: string, email: string}>}>  $prepared
     * @param  list<string>  $holdTokens
     * @return array<string, InventoryReservation>
     */
    private function holdsFor(array $prepared, array $holdTokens): array
    {
        $wanted = [];

        foreach ($prepared as $item) {
            $wanted[$item['type']->id] = $item['quantity'];
        }

        $matched = [];

        foreach ($holdTokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            $reservation = InventoryReservation::query()
                ->where('hold_token_hash', hash('sha256', $token))
                ->first();

            if (! $reservation instanceof InventoryReservation) {
                throw new CheckoutRejected('A reservation code is not valid.');
            }

            if (! isset($wanted[$reservation->ticket_type_id])) {
                throw new CheckoutRejected('A reservation does not match the tickets in this registration.');
            }

            if ($wanted[$reservation->ticket_type_id] !== $reservation->quantity) {
                throw new CheckoutRejected('A reservation quantity does not match this registration.');
            }

            $matched[$reservation->ticket_type_id] = $reservation;
        }

        return $matched;
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = strtoupper(Str::random(8));
            $taken = Order::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('reference', $reference)
                ->exists();

            if (! $taken) {
                return $reference;
            }
        }

        throw new CheckoutRejected('Could not assign an order reference.', 503);
    }
}
