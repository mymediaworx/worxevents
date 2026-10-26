<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domain\Payments\AuthorizeNetGateway;
use App\Domain\Payments\GatewayDisabled;
use App\Domain\Payments\ProviderResult;
use App\Domain\Payments\RefundInstruction;
use App\Support\CookieSecurity;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CookieSecurityTest extends TestCase
{
    public function test_production_cookies_are_secure_even_when_the_flag_is_off(): void
    {
        $this->assertTrue(CookieSecurity::secure('production', false));
        $this->assertTrue(CookieSecurity::secure('production', null));
        $this->assertFalse(CookieSecurity::secure('local', null));
        $this->assertTrue(CookieSecurity::secure('local', true));
    }

    public function test_a_gateway_cannot_report_a_refund_as_complete(): void
    {
        $this->expectException(LogicException::class);

        new ProviderResult(true, true, 'ref', 'no');
    }

    public function test_authorize_net_is_disabled(): void
    {
        $gateway = new AuthorizeNetGateway;

        $this->assertFalse($gateway->isReady());
        $this->expectException(GatewayDisabled::class);
        $gateway->refund(new RefundInstruction('org', 'order', 100, 'USD', 'key'));
    }
}
