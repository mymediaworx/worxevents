<?php

declare(strict_types=1);

namespace App\Domain\CheckIn;

use App\Domain\Catalog\CheckoutRejected;
use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One locked ticket row decides the door result. A second scan cannot
 * check the same ticket in again until a permitted reversal clears it.
 */
final class DoorCheckIn
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function scan(User $actor, Event $event, string $code): Ticket
    {
        $code = trim($code);

        if ($code === '') {
            throw new CheckoutRejected('Enter a ticket code.');
        }

        $hash = hash('sha256', $code);

        return DB::transaction(function () use ($actor, $event, $hash): Ticket {
            $ticket = Ticket::query()->where('token_hash', $hash)->lockForUpdate()->first();

            if (! $ticket instanceof Ticket) {
                $this->record($actor, $event, null, 'not_found');

                throw new CheckoutRejected('No ticket matches that code.', 404);
            }

            return $this->admit($actor, $event, $ticket);
        });
    }

    public function admitTicket(User $actor, Event $event, Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($actor, $event, $ticket): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked instanceof Ticket) {
                throw new CheckoutRejected('That ticket is not available.', 404);
            }

            return $this->admit($actor, $event, $locked);
        });
    }

    public function reverse(User $actor, Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked instanceof Ticket || $locked->checked_in_at === null) {
                throw new CheckoutRejected('That ticket is not checked in.', 409);
            }

            $event = Event::query()->whereKey($locked->event_id)->first();

            if (! $event instanceof Event) {
                throw new CheckoutRejected('That ticket is not checked in.', 409);
            }

            $locked->forceFill([
                'checked_in_at' => null,
                'checked_in_by_user_id' => null,
            ])->save();

            $this->record($actor, $event, $locked, 'reversed');
            $this->audit->write('checkin.reversed', $locked->organization_id, $locked, [
                'event_id' => $event->id,
            ]);

            return $locked;
        });
    }

    /**
     * @return Collection<int, Attendee>
     */
    public function lookup(Event $event, string $query): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return new Collection;
        }

        $like = '%'.addcslashes($query, '%_\\').'%';

        return Attendee::query()
            ->where('event_id', $event->id)
            ->where(function ($builder) use ($like, $query): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('order', function ($orders) use ($query): void {
                        $orders->where('reference', $query);
                    });
            })
            ->with(['order', 'tickets'])
            ->limit(20)
            ->get();
    }

    private function admit(User $actor, Event $event, Ticket $ticket): Ticket
    {
        if ($ticket->event_id !== $event->id) {
            $this->record($actor, $event, $ticket, 'wrong_event');

            throw new CheckoutRejected('That ticket is for a different event.', 409);
        }

        $order = Order::query()->whereKey($ticket->order_id)->lockForUpdate()->first();
        $eventRow = Event::query()->whereKey($event->id)->first();

        if ($order instanceof Order && ($order->status === OrderStatus::Canceled || ($eventRow instanceof Event && $eventRow->status === EventStatus::Canceled))) {
            $this->record($actor, $event, $ticket, 'canceled');

            throw new CheckoutRejected('That order or event was canceled.', 409);
        }

        if ($ticket->status === IssuedTicketStatus::Void && $order instanceof Order && $order->payment_status === PaymentStatus::Refunded) {
            $this->record($actor, $event, $ticket, 'refunded');

            throw new CheckoutRejected('That ticket was refunded.', 409);
        }

        if ($ticket->status !== IssuedTicketStatus::Issued || ($order instanceof Order && $order->status !== OrderStatus::Completed)) {
            $this->record($actor, $event, $ticket, 'canceled');

            throw new CheckoutRejected('That ticket is no longer valid.', 409);
        }

        if ($ticket->checked_in_at !== null) {
            $this->record($actor, $event, $ticket, 'duplicate');

            throw new CheckoutRejected('That ticket is already checked in.', 409);
        }

        $ticket->forceFill([
            'checked_in_at' => now(),
            'checked_in_by_user_id' => $actor->id,
        ])->save();

        $this->record($actor, $event, $ticket, 'checked_in');
        $this->audit->write('checkin.admitted', $ticket->organization_id, $ticket, [
            'event_id' => $event->id,
        ]);

        return $ticket;
    }

    private function record(User $actor, Event $event, ?Ticket $ticket, string $result): void
    {
        CheckIn::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'ticket_id' => $ticket?->id,
            'actor_user_id' => $actor->id,
            'result' => $result,
        ]);
    }
}
