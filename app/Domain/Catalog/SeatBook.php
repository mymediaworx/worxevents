<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\IssuedTicketStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Seat;
use App\Models\SeatingPlan;
use App\Models\SeatingRow;
use App\Models\SeatingSection;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Reserved seats are rows in a plan. The organizer edits sections, rows, and
 * seats. A visual seat designer is not part of this release.
 */
final class SeatBook
{
    public function hold(Seat $seat): Seat
    {
        return DB::transaction(function () use ($seat): Seat {
            $locked = $this->lock($seat);

            if ($locked->status !== 'available') {
                throw new CheckoutRejected('That seat is not available.', 409);
            }

            $locked->forceFill([
                'status' => 'held',
                'held_until' => now()->addMinutes(max(1, (int) config('worxevents.reservation_minutes', 15))),
            ])->save();

            return $locked;
        });
    }

    /**
     * @param  list<string>  $seatIds
     * @param  list<string>  $attendeeIds
     */
    public function claim(Event $event, array $seatIds, string $orderId, array $attendeeIds, bool $sold = true): void
    {
        $plan = SeatingPlan::query()->where('event_id', $event->id)->first();
        $expected = count($attendeeIds);

        if (! $plan instanceof SeatingPlan) {
            if ($seatIds !== []) {
                throw new CheckoutRejected('This event does not have reserved seats.');
            }

            return;
        }

        if (count($seatIds) !== $expected) {
            throw new CheckoutRejected('Choose one seat for each ticket.');
        }

        foreach ($seatIds as $index => $seatId) {
            $seat = Seat::query()->whereKey($seatId)->lockForUpdate()->first();

            if (! $seat instanceof Seat || ! $this->belongsToEvent($seat, $event)) {
                throw new CheckoutRejected('That seat is not part of this event.', 404);
            }

            if ($seat->status === 'sold') {
                throw new CheckoutRejected('That seat is not available.', 409);
            }

            if ($seat->status === 'held' && $seat->held_until !== null && ! $seat->held_until->isFuture()) {
                throw new CheckoutRejected('That seat hold has expired.', 409);
            }

            $seat->forceFill([
                'status' => $sold ? 'sold' : 'held',
                'order_id' => $orderId,
                'held_until' => $sold ? null : now()->addDays(2),
            ])->save();

            Attendee::query()->whereKey($attendeeIds[$index])->update(['seat_id' => $seat->id]);
            Ticket::query()
                ->where('attendee_id', $attendeeIds[$index])
                ->where('order_id', $orderId)
                ->update(['seat_id' => $seat->id]);
        }
    }

    public function finalize(string $orderId): void
    {
        $seats = Seat::query()->where('order_id', $orderId)->where('status', 'held')->orderBy('id')->get();

        foreach ($seats as $seat) {
            $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->first();

            if ($locked instanceof Seat && $locked->status === 'held') {
                $locked->forceFill([
                    'status' => 'sold',
                    'held_until' => null,
                ])->save();
            }
        }
    }

    public function releaseForOrder(string $orderId): void
    {
        $seats = Seat::query()->where('order_id', $orderId)->orderBy('id')->get();

        foreach ($seats as $seat) {
            $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->first();

            if (! $locked instanceof Seat || $locked->order_id !== $orderId) {
                continue;
            }

            if ($locked->status === 'sold' && Ticket::query()->where('seat_id', $locked->id)->where('status', IssuedTicketStatus::Issued)->exists()) {
                continue;
            }

            $locked->forceFill([
                'status' => 'available',
                'held_until' => null,
                'order_id' => null,
            ])->save();
        }

        Attendee::query()->where('order_id', $orderId)->update(['seat_id' => null]);
    }

    public function releaseExpired(): int
    {
        $seats = Seat::query()
            ->where('status', 'held')
            ->whereNull('order_id')
            ->whereNotNull('held_until')
            ->where('held_until', '<=', now())
            ->orderBy('id')
            ->get();

        $released = 0;

        foreach ($seats as $seat) {
            $did = DB::transaction(function () use ($seat): bool {
                $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->first();

                if (! $locked instanceof Seat || $locked->status !== 'held') {
                    return false;
                }

                if ($locked->order_id !== null || $locked->held_until === null || $locked->held_until->isFuture()) {
                    return false;
                }

                $locked->forceFill([
                    'status' => 'available',
                    'held_until' => null,
                ])->save();

                return true;
            });

            if ($did) {
                $released++;
            }
        }

        return $released;
    }

    private function lock(Seat $seat): Seat
    {
        $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->first();

        if (! $locked instanceof Seat) {
            throw new CheckoutRejected('That seat is not available.', 404);
        }

        return $locked;
    }

    private function belongsToEvent(Seat $seat, Event $event): bool
    {
        $row = SeatingRow::query()->whereKey($seat->seating_row_id)->first();

        if (! $row instanceof SeatingRow) {
            return false;
        }

        $section = SeatingSection::query()->whereKey($row->seating_section_id)->first();

        if (! $section instanceof SeatingSection) {
            return false;
        }

        return SeatingPlan::query()
            ->whereKey($section->seating_plan_id)
            ->where('event_id', $event->id)
            ->exists();
    }
}
