<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\EventStatus;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\InventoryReservation;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Inventory changes lock the event, then the session, then the ticket type.
 * Reservations store a hash of an opaque token and expire on a schedule.
 */
final class InventoryBook
{
    public function reserve(TicketType $type, int $quantity): Hold
    {
        if ($quantity < 1) {
            throw new CheckoutRejected('Quantity must be at least 1.');
        }

        return DB::transaction(function () use ($type, $quantity): Hold {
            $locked = $this->lockType($type);
            $this->assertAvailable($locked['event'], $locked['session'], $locked['type'], $quantity);
            $this->adjust($locked['event'], $locked['session'], $locked['type'], $quantity, 0);

            $token = bin2hex(random_bytes(32));
            $minutes = (int) config('worxevents.reservation_minutes', 15);

            $reservation = InventoryReservation::query()->create([
                'organization_id' => $locked['type']->organization_id,
                'ticket_type_id' => $locked['type']->id,
                'quantity' => $quantity,
                'hold_token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(max(1, $minutes)),
            ]);

            return new Hold($reservation, $token);
        });
    }

    public function consume(InventoryReservation $reservation, string $orderId): void
    {
        DB::transaction(function () use ($reservation, $orderId): void {
            $locked = $this->lockReservation($reservation->id);

            if ($locked['reservation']->consumed_at !== null || $locked['reservation']->released_at !== null) {
                throw new CheckoutRejected('That reservation is no longer active.', 409);
            }

            if (! $locked['reservation']->expires_at->isFuture()) {
                throw new CheckoutRejected('That reservation has expired.', 409);
            }

            $quantity = $locked['reservation']->quantity;
            $this->adjust($locked['event'], $locked['session'], $locked['type'], -$quantity, $quantity);
            $locked['reservation']->forceFill([
                'consumed_at' => now(),
                'order_id' => $orderId,
            ])->save();
            $this->refreshSaleStatus($locked['event']);
        });
    }

    public function claim(InventoryReservation $reservation, string $orderId): void
    {
        DB::transaction(function () use ($reservation, $orderId): void {
            $locked = $this->lockReservation($reservation->id);
            $row = $locked['reservation'];

            if ($row->released_at !== null || $row->consumed_at !== null) {
                throw new CheckoutRejected('That reservation is no longer active.', 409);
            }

            if ($row->order_id !== null && $row->order_id !== $orderId) {
                throw new CheckoutRejected('That reservation is already attached to an order.', 409);
            }

            $expires = $row->expires_at->lt(now()->addDays(2)) ? now()->addDays(2) : $row->expires_at;
            $row->forceFill([
                'order_id' => $orderId,
                'expires_at' => $expires,
            ])->save();
        });
    }

    public function release(InventoryReservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $locked = $this->lockReservation($reservation->id);
            $row = $locked['reservation'];

            if ($row->released_at !== null || $row->consumed_at !== null) {
                return;
            }

            $this->adjust($locked['event'], $locked['session'], $locked['type'], -$row->quantity, 0);
            $row->forceFill(['released_at' => now()])->save();
            $this->refreshSaleStatus($locked['event']);
        });
    }

    public function releaseForOrder(string $orderId): void
    {
        $ids = InventoryReservation::query()
            ->where('order_id', $orderId)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $reservation = InventoryReservation::query()->whereKey((string) $id)->first();

            if ($reservation instanceof InventoryReservation) {
                $this->release($reservation);
            }
        }
    }

    public function releaseExpired(): int
    {
        $ids = InventoryReservation::query()
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $released = 0;

        foreach ($ids as $id) {
            $did = DB::transaction(function () use ($id): bool {
                $locked = $this->lockReservation((string) $id);
                $reservation = $locked['reservation'];

                if ($reservation->released_at !== null || $reservation->consumed_at !== null) {
                    return false;
                }

                if ($reservation->expires_at->isFuture()) {
                    return false;
                }

                $this->adjust($locked['event'], $locked['session'], $locked['type'], -$reservation->quantity, 0);
                $reservation->forceFill(['released_at' => now()])->save();
                $this->refreshSaleStatus($locked['event']);

                return true;
            });

            if ($did) {
                $released++;
            }
        }

        return $released;
    }

    public function refreshSaleStatus(Event $event): void
    {
        if (! in_array($event->status, [EventStatus::Published, EventStatus::SoldOut], true)) {
            return;
        }

        $types = TicketType::query()
            ->where('event_id', $event->id)
            ->where('visibility', '!=', TicketVisibility::Hidden->value)
            ->get();

        if ($types->isEmpty()) {
            return;
        }

        $open = $types->contains(fn (TicketType $type): bool => ($type->quantity_total - $type->quantity_sold) > 0);
        $capacityOpen = $event->capacity === null || $event->admits_sold < $event->capacity;
        $next = ($open && $capacityOpen) ? EventStatus::Published : EventStatus::SoldOut;

        if ($event->status !== $next) {
            $event->status = $next;
            $event->save();
        }
    }

    /**
     * @return array{event: Event, session: ?EventSession, type: TicketType}
     */
    private function lockType(TicketType $type): array
    {
        $event = Event::query()->whereKey($type->event_id)->lockForUpdate()->first();

        if (! $event instanceof Event) {
            throw new CheckoutRejected('This event is not available.', 404);
        }

        $lockedType = TicketType::query()
            ->whereKey($type->id)
            ->where('event_id', $event->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedType instanceof TicketType) {
            throw new CheckoutRejected('That ticket is not available.', 404);
        }

        $session = null;

        if ($lockedType->session_id !== null) {
            $session = EventSession::query()->whereKey($lockedType->session_id)->lockForUpdate()->first();
        }

        return ['event' => $event, 'session' => $session, 'type' => $lockedType];
    }

    /**
     * @return array{event: Event, session: ?EventSession, type: TicketType, reservation: InventoryReservation}
     */
    private function lockReservation(string $id): array
    {
        $peek = InventoryReservation::query()->whereKey($id)->first();

        if (! $peek instanceof InventoryReservation) {
            throw new CheckoutRejected('That reservation is not available.', 404);
        }

        $type = TicketType::query()->whereKey($peek->ticket_type_id)->first();

        if (! $type instanceof TicketType) {
            throw new CheckoutRejected('That ticket is not available.', 404);
        }

        $locked = $this->lockType($type);
        $reservation = InventoryReservation::query()->whereKey($id)->lockForUpdate()->first();

        if (! $reservation instanceof InventoryReservation) {
            throw new CheckoutRejected('That reservation is not available.', 404);
        }

        return [
            'event' => $locked['event'],
            'session' => $locked['session'],
            'type' => $locked['type'],
            'reservation' => $reservation,
        ];
    }

    private function assertAvailable(Event $event, ?EventSession $session, TicketType $type, int $quantity): void
    {
        if ($type->remaining() < $quantity) {
            throw new CheckoutRejected('Not enough tickets remain.', 409);
        }

        $admits = $quantity * $type->admits;

        if ($event->capacity !== null && ($event->admits_sold + $event->admits_held + $admits) > $event->capacity) {
            throw new CheckoutRejected('This event is at capacity.', 409);
        }

        if ($session instanceof EventSession && $session->capacity !== null && ($session->admits_sold + $session->admits_held + $admits) > $session->capacity) {
            throw new CheckoutRejected('This session is at capacity.', 409);
        }
    }

    private function adjust(Event $event, ?EventSession $session, TicketType $type, int $heldDelta, int $soldDelta): void
    {
        $held = $type->quantity_held + $heldDelta;
        $sold = $type->quantity_sold + $soldDelta;

        if ($held < 0 || $sold < 0) {
            throw new CheckoutRejected('Inventory is out of balance.', 409);
        }

        $type->quantity_held = $held;
        $type->quantity_sold = $sold;
        $type->save();

        $admitsHeld = $event->admits_held + ($heldDelta * $type->admits);
        $admitsSold = $event->admits_sold + ($soldDelta * $type->admits);

        if ($admitsHeld < 0 || $admitsSold < 0) {
            throw new CheckoutRejected('Capacity is out of balance.', 409);
        }

        $event->admits_held = $admitsHeld;
        $event->admits_sold = $admitsSold;
        $event->save();

        if ($session instanceof EventSession) {
            $sessionHeld = $session->admits_held + ($heldDelta * $type->admits);
            $sessionSold = $session->admits_sold + ($soldDelta * $type->admits);

            if ($sessionHeld < 0 || $sessionSold < 0) {
                throw new CheckoutRejected('Session capacity is out of balance.', 409);
            }

            $session->admits_held = $sessionHeld;
            $session->admits_sold = $sessionSold;
            $session->save();
        }
    }
}
