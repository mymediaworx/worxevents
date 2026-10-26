<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Stripe Connect direct charges on the organizer's Standard account.
 *
 * The platform fee travels as application_fee_amount on the connected account.
 * This class does not call Stripe. Sandbox keys are not a reason to send a
 * charge from Phase 1. See docs/PAYMENTS.md.
 */
final class StripeConnectGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function isReady(): bool
    {
        return $this->secret() !== '' && $this->webhookSecret() !== '';
    }

    public function refund(RefundInstruction $instruction): ProviderResult
    {
        if (! $this->isReady()) {
            throw new GatewayUnconfigured('Stripe is not configured. Set STRIPE_SECRET and STRIPE_WEBHOOK_SECRET outside the repository.');
        }

        throw new GatewayDisabled('Stripe refund submission is not enabled in this phase. No request was sent.');
    }

    private function secret(): string
    {
        return trim((string) config('services.stripe.secret'));
    }

    private function webhookSecret(): string
    {
        return trim((string) config('services.stripe.webhook_secret'));
    }
}
