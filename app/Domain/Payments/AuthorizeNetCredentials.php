<?php

declare(strict_types=1);

namespace App\Domain\Payments;

final class AuthorizeNetCredentials
{
    public function __construct(
        public readonly string $apiLoginId,
        public readonly string $transactionKey,
        public readonly string $signatureKey,
        public readonly string $publicClientKey,
        public readonly string $environment,
    ) {}
}
