<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use App\Models\FeeRule;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class FeeRuleResolver
{
    public function effective(string $organizationId, DateTimeInterface $asOf): FeeRule
    {
        $moment = Carbon::instance(\DateTimeImmutable::createFromInterface($asOf))->utc();

        $rule = FeeRule::query()
            ->where(function ($query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            })
            ->where('effective_at', '<=', $moment)
            ->orderByRaw('case when organization_id is null then 1 else 0 end')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if (! $rule instanceof FeeRule) {
            throw new FeeRuleMissing('No fee rule is effective for this organization at the requested time.');
        }

        return $rule;
    }
}
