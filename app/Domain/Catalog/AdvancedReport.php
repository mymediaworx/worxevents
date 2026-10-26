<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\IssuedTicketStatus;
use App\Models\Order;
use App\Models\OrderAddOn;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\WaitlistEntry;
use App\Tenancy\OrganizationManager;

final class AdvancedReport
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    /**
     * @return array{discount_cents: int, add_on_cents: int, waitlist_waiting: int, seats_sold: int, seats_open: int, checked_in: int, tickets_issued: int}
     */
    public function summarize(): array
    {
        if ($this->organizations->id() === null) {
            throw new \RuntimeException('Choose an organization before reading reports.');
        }

        return [
            'discount_cents' => (int) Order::query()->sum('discount_cents'),
            'add_on_cents' => (int) OrderAddOn::query()->sum('line_total_cents'),
            'waitlist_waiting' => WaitlistEntry::query()->where('status', 'waiting')->count(),
            'seats_sold' => Seat::query()->where('status', 'sold')->count(),
            'seats_open' => Seat::query()->where('status', 'available')->count(),
            'checked_in' => Ticket::query()->whereNotNull('checked_in_at')->count(),
            'tickets_issued' => Ticket::query()->where('status', IssuedTicketStatus::Issued->value)->count(),
        ];
    }
}
