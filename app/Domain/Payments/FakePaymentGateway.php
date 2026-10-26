<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Test double. Accepts a refund and returns a reference. It never completes one.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function isReady(): bool
    {
        return true;
    }

    public function refund(RefundInstruction $instruction): ProviderResult
    {
        return new ProviderResult(
            accepted: true,
            completed: false,
            reference: 'fake_'.$instruction->idempotencyKey,
            detail: 'Accepted for test. Waiting for provider confirmation.',
        );
    }
}
