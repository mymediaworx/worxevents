<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use InvalidArgumentException;

final class FeeRuleData
{
    public function __construct(
        public readonly int $percentBps,
        public readonly int $minimumCents,
        public readonly int $maximumCents,
        public readonly string $currency = 'USD',
        public readonly ?string $id = null,
    ) {
        if ($percentBps < 0 || $percentBps > 10_000) {
            throw new InvalidArgumentException('Percent basis points must be between 0 and 10000.');
        }

        if ($minimumCents < 0 || $maximumCents < 0) {
            throw new InvalidArgumentException('Fee bounds cannot be negative.');
        }

        if ($minimumCents > $maximumCents) {
            throw new InvalidArgumentException('The fee minimum cannot exceed the maximum.');
        }

        if ($currency !== 'USD') {
            throw new InvalidArgumentException('Phase 1 fee rules are USD only.');
        }
    }
}
