<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Models\Order;
use App\Models\PaymentSetting;
use App\Tenancy\OrganizationManager;
use Illuminate\Container\Container;
use Throwable;

/**
 * Authorize.net Accept.js on the organizer's own merchant account.
 *
 * There is no platform split. The booking fee is a receivable. Live mode
 * stays off until payments.authorize_net_live is enabled. Card numbers are
 * never accepted.
 */
final class AuthorizeNetGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?AuthorizeNetClient $client = null,
        private readonly ?OrganizationManager $organizations = null,
    ) {}

    public function name(): string
    {
        return 'authorize_net';
    }

    public function isReady(): bool
    {
        try {
            $setting = $this->currentSetting();

            return $setting instanceof PaymentSetting && $this->settingReady($setting);
        } catch (Throwable) {
            return false;
        }
    }

    public function refund(RefundInstruction $instruction): ProviderResult
    {
        if (! $this->isReady()) {
            throw new GatewayDisabled('Authorize.net is not enabled. Card data is never accepted by this application.');
        }

        $credentials = $this->credentialsFor($instruction->organizationId);
        $order = Order::query()->whereKey($instruction->orderKey)->first();

        if (! $order instanceof Order || $order->provider !== 'authorize_net' || $order->provider_reference === null) {
            throw new GatewayDisabled('That order has no Authorize.net transaction to refund.');
        }

        if ($instruction->amountCents > $order->total_cents) {
            throw new GatewayDisabled('The refund is larger than the charge.');
        }

        return $this->resolveClient()->refund(
            $credentials,
            $order->provider_reference,
            $instruction,
            $order->card_last_four,
        );
    }

    public function charge(ChargeInstruction $instruction): ProviderResult
    {
        return $this->resolveClient()->charge($this->credentialsFor($instruction->organizationId), $instruction);
    }

    public function lookup(string $organizationId, string $transactionId): ProviderResult
    {
        return $this->resolveClient()->fetch($this->credentialsFor($organizationId), $transactionId);
    }

    public function validateConnection(AuthorizeNetCredentials $credentials): bool
    {
        $this->assertEnvironmentAllowed($credentials->environment);

        return $this->resolveClient()->authenticate($credentials);
    }

    public function credentialsFor(string $organizationId): AuthorizeNetCredentials
    {
        $current = $this->organizationId();

        if ($current === null || $current !== $organizationId) {
            throw new GatewayDisabled('Authorize.net is not enabled. Card data is never accepted by this application.');
        }

        $setting = $this->currentSetting();

        if (! $setting instanceof PaymentSetting || ! $this->settingReady($setting)) {
            throw new GatewayDisabled('Authorize.net is not enabled. Card data is never accepted by this application.');
        }

        return new AuthorizeNetCredentials(
            apiLoginId: (string) $setting->api_login_id,
            transactionKey: (string) $setting->transaction_key,
            signatureKey: (string) $setting->signature_key,
            publicClientKey: (string) $setting->public_client_key,
            environment: (string) $setting->environment,
        );
    }

    private function settingReady(PaymentSetting $setting): bool
    {
        if ($setting->validated_at === null) {
            return false;
        }

        if ((string) $setting->api_login_id === '' || (string) $setting->transaction_key === '' || (string) $setting->public_client_key === '') {
            return false;
        }

        try {
            $this->assertEnvironmentAllowed((string) $setting->environment);
        } catch (GatewayDisabled) {
            return false;
        }

        return true;
    }

    private function assertEnvironmentAllowed(string $environment): void
    {
        if ($environment === 'sandbox') {
            return;
        }

        if ($environment === 'live' && config('payments.authorize_net_live') === true) {
            return;
        }

        throw new GatewayDisabled('Live Authorize.net mode is disabled until the platform enables it.');
    }

    private function currentSetting(): ?PaymentSetting
    {
        $organizationId = $this->organizationId();

        if ($organizationId === null) {
            return null;
        }

        $setting = PaymentSetting::query()->where('organization_id', $organizationId)->first();

        return $setting instanceof PaymentSetting ? $setting : null;
    }

    private function organizationId(): ?string
    {
        if ($this->organizations instanceof OrganizationManager) {
            return $this->organizations->id();
        }

        if (! Container::getInstance()->bound(OrganizationManager::class)) {
            return null;
        }

        return Container::getInstance()->make(OrganizationManager::class)->id();
    }

    private function resolveClient(): AuthorizeNetClient
    {
        if ($this->client instanceof AuthorizeNetClient) {
            return $this->client;
        }

        return Container::getInstance()->make(AuthorizeNetClient::class);
    }
}
