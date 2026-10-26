<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\BookingFeeRecorder;
use App\Domain\Fees\FeeBasis;
use App\Domain\Tickets\PassFactory;
use App\Domain\Tickets\TicketPass;
use App\Enums\FeeLedgerKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderAddOn;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Issues tickets only after an approved processor result. A repeated call
 * for the same transaction does not issue a second set of tickets.
 */
final class OrderSettlement
{
    public function __construct(
        private readonly InventoryBook $inventory,
        private readonly PassFactory $passes,
        private readonly BookingFeeRecorder $fees,
        private readonly AuditLogger $audit,
        private readonly SeatBook $seats,
        private readonly AddOnBook $addOns,
        private readonly DiscountBook $discounts,
    ) {}

    /**
     * @return array{order: Order, passes: list<TicketPass>, created: bool}
     */
    public function approve(Order $order, string $reference, ?string $lastFour): array
    {
        /** @var list<TicketPass> $issued */
        $issued = [];
        $created = false;

        $saved = DB::transaction(function () use ($order, $reference, $lastFour, &$issued, &$created): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked instanceof Order) {
                throw new CheckoutRejected('This order is not available.', 404);
            }

            if ($locked->status === OrderStatus::Completed) {
                $stored = (string) $locked->provider_reference;

                if ($locked->provider_reference !== null && strlen($stored) === strlen($reference) && hash_equals($stored, $reference)) {
                    return $locked;
                }

                throw new CheckoutRejected('This order was already completed.', 409);
            }

            if ($locked->status !== OrderStatus::Pending) {
                throw new CheckoutRejected('This order cannot be completed.', 409);
            }

            $this->seats->finalize($locked->id);
            $attendees = Attendee::query()->where('order_id', $locked->id)->get();

            foreach ($attendees as $attendee) {
                $event = Event::query()->whereKey($attendee->event_id)->first();

                if (! $event instanceof Event) {
                    throw new CheckoutRejected('The event for this order is missing.', 409);
                }

                $issued[] = $this->passes->issue($attendee, $event, $locked->reference);
            }

            $reservations = InventoryReservation::query()
                ->where('order_id', $locked->id)
                ->whereNull('consumed_at')
                ->whereNull('released_at')
                ->get();

            foreach ($reservations as $reservation) {
                $this->inventory->consume($reservation, $locked->id);
            }

            $addOnCents = (int) OrderAddOn::query()->where('order_id', $locked->id)->sum('line_total_cents');
            $snapshot = $this->fees->record(
                $locked->organization_id,
                $locked->id,
                new FeeBasis($locked->ticket_subtotal_cents, $locked->discount_cents, 0, 0, $addOnCents, 0),
                $locked->created_at,
            );

            if ($snapshot->fee_cents !== $locked->fee_cents) {
                throw new CheckoutRejected('The booking fee changed after the charge was quoted.', 409);
            }

            $this->fees->postLedger(
                $locked->organization_id,
                $locked->id,
                FeeLedgerKind::Owed,
                $snapshot->fee_cents,
                $snapshot->currency,
                $locked->organization_id.':'.$locked->id.':owed',
                $snapshot->id,
            );

            $digits = $lastFour !== null && preg_match('/^\d{4}$/', $lastFour) === 1 ? $lastFour : null;
            $locked->forceFill([
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Approved,
                'provider_reference' => $reference,
                'card_last_four' => $digits,
                'completed_at' => now(),
            ])->save();

            $this->audit->write('order.completed', $locked->organization_id, $locked, [
                'reference' => $locked->reference,
                'total_cents' => $locked->total_cents,
                'fee_cents' => $locked->fee_cents,
                'tickets' => count($issued),
                'provider' => 'authorize_net',
            ]);

            $created = true;

            return $locked;
        });

        return ['order' => $saved, 'passes' => $issued, 'created' => $created];
    }

    /**
     * Completes an order whose server total is zero, such as a fully discounted
     * ticket with no add-ons. No processor call is made and no fee is owed.
     *
     * @return array{order: Order, passes: list<TicketPass>}
     */
    public function completeWithoutCharge(Order $order): array
    {
        /** @var list<TicketPass> $issued */
        $issued = [];

        $saved = DB::transaction(function () use ($order, &$issued): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked instanceof Order || $locked->status !== OrderStatus::Pending) {
                throw new CheckoutRejected('This order cannot be completed.', 409);
            }

            if ($locked->total_cents !== 0) {
                throw new CheckoutRejected('This order still has a balance.', 409);
            }

            $this->seats->finalize($locked->id);
            $attendees = Attendee::query()->where('order_id', $locked->id)->get();

            foreach ($attendees as $attendee) {
                $event = Event::query()->whereKey($attendee->event_id)->first();

                if (! $event instanceof Event) {
                    throw new CheckoutRejected('The event for this order is missing.', 409);
                }

                $issued[] = $this->passes->issue($attendee, $event, $locked->reference);
            }

            $reservations = InventoryReservation::query()
                ->where('order_id', $locked->id)
                ->whereNull('consumed_at')
                ->whereNull('released_at')
                ->get();

            foreach ($reservations as $reservation) {
                $this->inventory->consume($reservation, $locked->id);
            }

            $addOnCents = (int) OrderAddOn::query()->where('order_id', $locked->id)->sum('line_total_cents');
            $snapshot = $this->fees->record(
                $locked->organization_id,
                $locked->id,
                new FeeBasis($locked->ticket_subtotal_cents, $locked->discount_cents, 0, 0, $addOnCents, 0),
                $locked->created_at,
            );

            if ($snapshot->fee_cents !== $locked->fee_cents) {
                throw new CheckoutRejected('The booking fee changed after the order was quoted.', 409);
            }

            $locked->forceFill([
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::None,
                'provider' => null,
                'completed_at' => now(),
            ])->save();

            $this->audit->write('order.completed', $locked->organization_id, $locked, [
                'reference' => $locked->reference,
                'total_cents' => 0,
                'tickets' => count($issued),
            ]);

            return $locked;
        });

        return ['order' => $saved, 'passes' => $issued];
    }

    public function decline(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked instanceof Order || $locked->status !== OrderStatus::Pending) {
                return;
            }

            $this->inventory->releaseForOrder($locked->id);
            $this->seats->releaseForOrder($locked->id);
            $this->addOns->restore($locked);
            $eventId = Attendee::query()->where('order_id', $locked->id)->value('event_id');
            $event = Event::query()->whereKey($eventId)->first();

            if ($event instanceof Event) {
                $code = $locked->discount_code;
                $this->discounts->restore($event, is_string($code) ? $code : null);
            }

            $locked->forceFill([
                'status' => OrderStatus::Canceled,
                'payment_status' => PaymentStatus::Declined,
            ])->save();

            $this->audit->write('order.payment_declined', $locked->organization_id, $locked, [
                'reference' => $locked->reference,
            ]);
        });
    }
}
