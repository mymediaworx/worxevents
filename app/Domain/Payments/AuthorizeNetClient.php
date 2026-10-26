<?php

declare(strict_types=1);

namespace App\Domain\Payments;

interface AuthorizeNetClient
{
    public function authenticate(AuthorizeNetCredentials $credentials): bool;

    public function charge(AuthorizeNetCredentials $credentials, ChargeInstruction $instruction): ProviderResult;

    public function refund(
        AuthorizeNetCredentials $credentials,
        string $transactionId,
        RefundInstruction $instruction,
        ?string $cardLastFour,
    ): ProviderResult;

    public function fetch(AuthorizeNetCredentials $credentials, string $transactionId): ProviderResult;
}
