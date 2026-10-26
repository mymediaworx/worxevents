<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\BookingFeeRecorder;
use App\Domain\Fees\FeeBasis;
use App\Domain\Growth\CampaignAttribution;
use App\Domain\Tickets\PassFactory;
use App\Domain\Tickets\TicketPass;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;

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
        private readonly CheckoutCart $cart,
        private readonly DiscountBook $discounts,
        private readonly AddOnBook $addOns,
        private readonly SeatBook $seats,
        private readonly CampaignAttribution $campaign,
    ) {}

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @param  array<string, string>  $answers
     * @param  list<string>  $holdTokens
     * @param  array<string, int>  $addOnSelections
     * @param  list<string>  $seatIds
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
        ?string $discountCode = null,
        array $addOnSelections = [],
        array $seatIds = [],
    ): array {
        $this->assertContext($event);

        if ($lines === []) {
            throw new CheckoutRejected('Choose at least one ticket.');
        }

        /** @var list<TicketPass> $issued */
        $issued = [];

        $order = DB::transaction(function () use ($event, $lines, $answers, $guestName, $guestEmail, $buyerUserId, $holdTokens, $discountCode, $addOnSelections, $seatIds, &$issued): Order {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $lockedEvent instanceof Event) {
                throw new CheckoutRejected('This event is not available.', 404);
            }

            $prepared = $this->cart->prepareLines($lockedEvent, $lines, true);
            $answerRows = $this->cart->prepareAnswers($lockedEvent, $answers);
            $reservations = $this->cart->holdsFor($prepared, $holdTokens);

            foreach ($prepared as $item) {
                if (! isset($reservations[$item['type']->id])) {
                    $hold = $this->inventory->reserve($item['type'], $item['quantity']);
                    $reservations[$item['type']->id] = $hold->reservation;
                }
            }

            $subtotal = $this->cart->subtotal($prepared);
            $typeIds = [];

            foreach ($prepared as $item) {
                $typeIds[] = $item['type']->id;
            }

            $addOn = $this->addOns->take($lockedEvent, $addOnSelections);
            $discount = $this->discounts->redeem($lockedEvent, $subtotal, $typeIds, $discountCode);

            $order = Order::query()->create([
                'organization_id' => $lockedEvent->organization_id,
                'buyer_user_id' => $buyerUserId,
                'guest_name' => $guestName,
                'guest_email' => strtolower($guestEmail),
                'status' => OrderStatus::Completed,
                'channel' => 'online',
                ...$this->campaign->forOrder(),
                'currency' => 'USD',
                'ticket_subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'discount_code' => $discount > 0 ? strtoupper(trim((string) $discountCode)) : null,
                'fee_cents' => 0,
                'total_cents' => $subtotal - $discount + $addOn['cents'],
                'reference' => $this->cart->reference(),
                'completed_at' => now(),
            ]);
            $attendeeIds = [];

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
                    $attendeeIds[] = $attendee->id;
                }
            }

            $this->addOns->snapshot($order, $addOn['rows']);
            $this->seats->claim($lockedEvent, $seatIds, $order->id, $attendeeIds);

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
                new FeeBasis($subtotal, $discount, 0, 0, $addOn['cents'], 0),
                now(),
            );
            $total = $subtotal - $discount + $addOn['cents'] + $snapshot->fee_cents;

            $order->forceFill([
                'fee_cents' => $snapshot->fee_cents,
                'total_cents' => $total,
            ])->save();

            $this->audit->write('order.completed', $lockedEvent->organization_id, $order, [
                'reference' => $order->reference,
                'total_cents' => $total,
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
}
