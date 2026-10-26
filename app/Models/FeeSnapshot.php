<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FeeSnapshot extends Model
{
    use BelongsToOrganization, HasUlids, ImmutableRecord;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'order_key',
        'fee_rule_id',
        'currency',
        'ticket_subtotal_cents',
        'discount_cents',
        'eligible_cents',
        'merchandise_cents',
        'donation_cents',
        'add_on_cents',
        'tax_cents',
        'fee_cents',
        'percent_bps',
        'minimum_cents',
        'maximum_cents',
        'rounding',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'eligible_cents' => 'integer',
            'merchandise_cents' => 'integer',
            'donation_cents' => 'integer',
            'add_on_cents' => 'integer',
            'tax_cents' => 'integer',
            'fee_cents' => 'integer',
            'percent_bps' => 'integer',
            'minimum_cents' => 'integer',
            'maximum_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
