<?php

declare(strict_types=1);

namespace App\Domain\Fees;

final class FeeQuote
{
    public function __construct(
        public readonly int $feeCents,
        public readonly int $eligibleCents,
        public readonly int $rawPercentCents,
        public readonly FeeRuleData $rule,
    ) {}

    public function isFree(): bool
    {
        return $this->feeCents === 0;
    }
}
