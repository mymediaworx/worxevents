<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\EventStatus;
use App\Enums\QuestionField;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\TicketType;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Str;

/**
 * Shared cart checks for free registration and paid checkout.
 */
final class CheckoutCart
{
    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @return list<array{type: TicketType, quantity: int, attendees: list<array{name: string, email: string}>}>
     */
    public function prepareLines(Event $event, array $lines, bool $freeOnly): array
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

            if ($freeOnly && $type->price_cents !== 0) {
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

    /**
     * @param  array<string, string>  $answers
     * @return list<array{id: string, value: string}>
     */
    public function prepareAnswers(Event $event, array $answers): array
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
    public function holdsFor(array $prepared, array $holdTokens): array
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

    public function reference(): string
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

    /**
     * @param  list<array{type: TicketType, quantity: int, attendees: list<array{name: string, email: string}>}>  $prepared
     */
    public function subtotal(array $prepared): int
    {
        $subtotal = 0;

        foreach ($prepared as $item) {
            $subtotal += $item['type']->price_cents * $item['quantity'];
        }

        return $subtotal;
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
}
