<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'ticket_type_id',
        'code',
        'kind',
        'amount',
        'max_redemptions',
        'redeemed_count',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
