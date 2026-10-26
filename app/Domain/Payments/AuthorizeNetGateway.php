<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Authorize.net Accept.js on the organizer's own merchant account.
 *
 * There is no platform split. The booking fee is a receivable, not an
 * application fee. This adapter stays disabled until that phase is approved.
 */
final class AuthorizeNetGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'authorize_net';
    }

    public function isReady(): bool
    {
        return false;
    }

    public function refund(RefundInstruction $instruction): ProviderResult
    {
        throw new GatewayDisabled('Authorize.net is not enabled. Card data is never accepted by this application.');
    }
}
