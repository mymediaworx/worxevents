<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Collection;

final class BasicReport
{
    /**
     * @return array{published_events: int, completed_orders: int, tickets_issued: int, ticket_subtotal_cents: int, fee_cents: int}
     */
    public function summarize(): array
    {
        $completed = Order::query()->where('status', OrderStatus::Completed);

        return [
            'published_events' => Event::query()->where('status', EventStatus::Published)->count(),
            'completed_orders' => (clone $completed)->count(),
            'tickets_issued' => Ticket::query()->where('status', IssuedTicketStatus::Issued)->count(),
            'ticket_subtotal_cents' => (int) (clone $completed)->sum('ticket_subtotal_cents'),
            'fee_cents' => (int) (clone $completed)->sum('fee_cents'),
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    public function events(): Collection
    {
        return Event::query()
            ->with(['ticketTypes', 'venue'])
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();
    }
}
