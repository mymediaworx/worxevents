<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use RuntimeException;

/**
 * In-memory Authorize.net stand-in. It records no card numbers and sends no HTTP.
 */
final class FakeAuthorizeNetClient implements AuthorizeNetClient
{
    /**
     * @var array<string, ProviderResult>
     */
    private array $transactions = [];

    public function authenticate(AuthorizeNetCredentials $credentials): bool
    {
        $this->guard();

        return $credentials->apiLoginId !== 'invalid'
            && $credentials->transactionKey !== ''
            && $credentials->signatureKey !== ''
            && $credentials->publicClientKey !== '';
    }

    public function charge(AuthorizeNetCredentials $credentials, ChargeInstruction $instruction): ProviderResult
    {
        $this->guard();

        if (str_starts_with($instruction->dataValue, 'decline')) {
            return new ProviderResult(false, false, null, 'Declined.', 'declined');
        }

        $reference = 'anet_'.$instruction->idempotencyKey;
        $result = new ProviderResult(
            accepted: true,
            completed: false,
            reference: $reference,
            detail: 'Approved in the test processor.',
            status: 'approved',
            accountLastFour: '4242',
            invoiceNumber: $instruction->invoiceNumber,
        );
        $this->transactions[$reference] = $result;

        if ($instruction->dataValue === 'timeout-after-approve') {
            throw new RuntimeException('Authorize.net did not respond.');
        }

        return $result;
    }

    public function refund(
        AuthorizeNetCredentials $credentials,
        string $transactionId,
        RefundInstruction $instruction,
        ?string $cardLastFour,
    ): ProviderResult {
        $this->guard();

        if (! isset($this->transactions[$transactionId])) {
            return new ProviderResult(false, false, null, 'Original transaction was not found.', 'declined');
        }

        if ($instruction->amountCents < 1) {
            return new ProviderResult(false, false, null, 'Refund amount must be at least one cent.', 'declined');
        }

        $reference = 'anet_rf_'.$instruction->idempotencyKey;
        $result = new ProviderResult(
            accepted: true,
            completed: false,
            reference: $reference,
            detail: 'Refund accepted in the test processor.',
            status: 'approved',
            invoiceNumber: $this->transactions[$transactionId]->invoiceNumber,
        );
        $this->transactions[$reference] = $result;

        return $result;
    }

    public function fetch(AuthorizeNetCredentials $credentials, string $transactionId): ProviderResult
    {
        $this->guard();

        return $this->transactions[$transactionId] ?? new ProviderResult(
            accepted: false,
            completed: false,
            reference: null,
            detail: 'Transaction was not found.',
            status: 'declined',
        );
    }

    private function guard(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('The fake Authorize.net client is only available in tests.');
        }
    }
}
