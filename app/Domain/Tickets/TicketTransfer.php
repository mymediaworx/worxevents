<?php

declare(strict_types=1);

namespace App\Domain\Tickets;

use App\Domain\Catalog\CheckoutRejected;
use App\Enums\IssuedTicketStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * A transfer voids the old token and issues a new one. The old hash stays
 * in the database so it cannot be checked in or reused.
 */
final class TicketTransfer
{
    public function __construct(
        private readonly PassFactory $passes,
        private readonly AuditLogger $audit,
    ) {}

    public function transfer(Ticket $ticket, string $name, string $email): TicketPass
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckoutRejected('Enter the new holder\'s name and email.');
        }

        return DB::transaction(function () use ($ticket, $name, $email): TicketPass {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked instanceof Ticket || $locked->status !== IssuedTicketStatus::Issued) {
                throw new CheckoutRejected('That ticket cannot be transferred.', 409);
            }

            if ($locked->checked_in_at !== null) {
                throw new CheckoutRejected('A checked-in ticket cannot be transferred.', 409);
            }

            $attendee = Attendee::query()->whereKey($locked->attendee_id)->lockForUpdate()->first();
            $event = Event::query()->whereKey($locked->event_id)->first();
            $order = Order::query()->whereKey($locked->order_id)->first();

            if (! $attendee instanceof Attendee || ! $event instanceof Event || ! $order instanceof Order) {
                throw new CheckoutRejected('That ticket cannot be transferred.', 409);
            }

            $locked->forceFill(['status' => IssuedTicketStatus::Void])->save();
            $attendee->forceFill([
                'name' => $name,
                'email' => $email,
            ])->save();

            $pass = $this->passes->issue($attendee, $event, $order->reference);
            $this->audit->write('ticket.transferred', $locked->organization_id, $locked, [
                'event_id' => $event->id,
            ]);

            return $pass;
        });
    }
}
