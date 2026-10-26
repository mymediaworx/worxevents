<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'ticket_type_id',
        'order_id',
        'quantity',
        'hold_token_hash',
        'expires_at',
        'released_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function isOpen(): bool
    {
        return $this->released_at === null
            && $this->consumed_at === null
            && $this->expires_at->isFuture();
    }
}
