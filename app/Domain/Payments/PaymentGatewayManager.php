<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use RuntimeException;

final class PaymentGatewayManager
{
    public function currentName(): string
    {
        $name = (string) config('payments.default', 'stripe');

        if ($name === 'fake' && ! app()->environment('testing')) {
            throw new RuntimeException('The fake gateway is only available in tests.');
        }

        if (! in_array($name, ['stripe', 'authorize_net', 'fake'], true)) {
            throw new RuntimeException('Unknown payment gateway.');
        }

        return $name;
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= $this->currentName();

        return match ($name) {
            'stripe' => app(StripeConnectGateway::class),
            'authorize_net' => app(AuthorizeNetGateway::class),
            'fake' => app(FakePaymentGateway::class),
            default => throw new RuntimeException('Unknown payment gateway.'),
        };
    }
}
