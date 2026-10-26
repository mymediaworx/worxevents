<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeLedgerKind;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FeeLedgerEntry extends Model
{
    use BelongsToOrganization, HasUlids, ImmutableRecord;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fee_snapshot_id',
        'order_key',
        'kind',
        'amount_cents',
        'currency',
        'idempotency_key',
        'occurred_at',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeeLedgerKind::class,
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
