<?php

declare(strict_types=1);

namespace App\Domain\Payments;

final class RefundInstruction
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $orderKey,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $idempotencyKey,
    ) {}
}
