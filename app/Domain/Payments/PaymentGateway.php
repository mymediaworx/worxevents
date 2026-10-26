<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Organizer-owned payment providers sit behind this interface.
 *
 * Checkout is intentionally not on the interface yet. Phase 1 records the
 * booking fee and the refund decision. It does not capture cards. A later
 * phase adds a charge method that Stripe implements first. Authorize.net
 * implements the same method afterward. Neither method may mark a refund
 * complete. Completion is a separate confirmation step.
 */
interface PaymentGateway
{
    public function name(): string;

    public function isReady(): bool;

    public function refund(RefundInstruction $instruction): ProviderResult;
}
