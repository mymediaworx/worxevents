<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fees\FeeRuleData;
use App\Models\Concerns\ImmutableRecord;
use App\Tenancy\FeeRuleScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FeeRule extends Model
{
    use HasUlids, ImmutableRecord;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'percent_bps',
        'minimum_cents',
        'maximum_cents',
        'currency',
        'effective_at',
        'created_by_user_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_bps' => 'integer',
            'minimum_cents' => 'integer',
            'maximum_cents' => 'integer',
            'effective_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new FeeRuleScope);
    }

    public function toData(): FeeRuleData
    {
        return new FeeRuleData(
            percentBps: $this->percent_bps,
            minimumCents: $this->minimum_cents,
            maximumCents: $this->maximum_cents,
            currency: $this->currency,
            id: $this->id,
        );
    }
}
