<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * A charge uses an Accept.js opaque nonce. It never carries a card number.
 */
final class ChargeInstruction
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $orderKey,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $dataDescriptor,
        public readonly string $dataValue,
        public readonly string $invoiceNumber,
        public readonly string $description,
    ) {}
}
