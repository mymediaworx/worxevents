<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'session_id',
        'name',
        'kind',
        'description',
        'price_cents',
        'currency',
        'quantity_total',
        'quantity_sold',
        'quantity_held',
        'admits',
        'min_per_order',
        'max_per_order',
        'sales_starts_at',
        'sales_ends_at',
        'visibility',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TicketKind::class,
            'visibility' => TicketVisibility::class,
            'price_cents' => 'integer',
            'quantity_total' => 'integer',
            'quantity_sold' => 'integer',
            'quantity_held' => 'integer',
            'admits' => 'integer',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'sales_starts_at' => 'datetime',
            'sales_ends_at' => 'datetime',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    public function remaining(): int
    {
        return max(0, $this->quantity_total - $this->quantity_sold - $this->quantity_held);
    }
}
