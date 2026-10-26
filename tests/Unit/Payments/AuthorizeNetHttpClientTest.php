<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Domain\Payments\AuthorizeNetCredentials;
use App\Domain\Payments\ChargeInstruction;
use App\Domain\Payments\GatewayDisabled;
use App\Domain\Payments\HttpAuthorizeNetClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AuthorizeNetHttpClientTest extends TestCase
{
    public function test_sandbox_charge_sends_an_opaque_nonce_and_not_a_card_number(): void
    {
        Http::fake([
            'https://apitest.authorize.net/*' => Http::response([
                'transactionResponse' => [
                    'responseCode' => '1',
                    'transId' => '999',
                    'accountNumber' => 'XXXX4242',
                ],
                'messages' => ['resultCode' => 'Ok'],
            ]),
        ]);

        $result = (new HttpAuthorizeNetClient)->charge($this->credentials('sandbox'), $this->instruction());

        $this->assertTrue($result->accepted);
        $this->assertFalse($result->completed);
        $this->assertSame('approved', $result->status);
        $this->assertSame('999', $result->reference);
        $this->assertSame('4242', $result->accountLastFour);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($request->url(), 'https://apitest.authorize.net/')
                && str_contains($body, 'opaqueData')
                && str_contains($body, 'COMMON.ACCEPT.INAPP.PAYMENT')
                && str_contains($body, '51.50')
                && ! str_contains($body, '4111111111111111')
                && ! str_contains($request->url(), 'https://api.authorize.net/');
        });
    }

    public function test_live_mode_is_refused_until_the_platform_enables_it(): void
    {
        config(['payments.authorize_net_live' => false]);
        Http::fake();

        $this->expectException(GatewayDisabled::class);

        try {
            (new HttpAuthorizeNetClient)->charge($this->credentials('live'), $this->instruction());
        } finally {
            Http::assertNothingSent();
        }
    }

    private function credentials(string $environment): AuthorizeNetCredentials
    {
        return new AuthorizeNetCredentials('login', 'transaction', 'signature', 'public', $environment);
    }

    private function instruction(): ChargeInstruction
    {
        return new ChargeInstruction(
            organizationId: 'org',
            orderKey: 'order',
            amountCents: 5150,
            currency: 'USD',
            idempotencyKey: 'order',
            dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT',
            dataValue: 'opaque-token-value',
            invoiceNumber: 'ABCD1234',
            description: 'Tickets',
        );
    }
}
