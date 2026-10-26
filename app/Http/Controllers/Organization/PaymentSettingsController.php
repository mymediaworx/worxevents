<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Payments\AuthorizeNetCredentials;
use App\Domain\Payments\AuthorizeNetGateway;
use App\Domain\Payments\GatewayDisabled;
use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly AuthorizeNetGateway $gateway,
        private readonly OrganizationManager $organizations,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): Response
    {
        return response()->view('organization.payments', $this->pageData());
    }

    public function store(Request $request): Response
    {
        $validated = $this->validateOr422($request, [
            'environment' => ['required', 'in:sandbox,live'],
            'api_login_id' => ['required', 'string', 'max:100'],
            'transaction_key' => ['required', 'string', 'max:100'],
            'signature_key' => ['required', 'string', 'max:200'],
            'public_client_key' => ['required', 'string', 'max:200'],
        ], 'organization.payments', $this->pageData());

        $environment = (string) $validated['environment'];

        if ($environment === 'live' && config('payments.authorize_net_live') !== true) {
            return response()->view('organization.payments', array_merge($this->pageData(), [
                'message' => 'Live Authorize.net mode is disabled until the platform enables it.',
            ]), 422);
        }

        $credentials = new AuthorizeNetCredentials(
            apiLoginId: (string) $validated['api_login_id'],
            transactionKey: (string) $validated['transaction_key'],
            signatureKey: (string) $validated['signature_key'],
            publicClientKey: (string) $validated['public_client_key'],
            environment: $environment,
        );

        try {
            $valid = $this->gateway->validateConnection($credentials);
        } catch (GatewayDisabled $exception) {
            return response()->view('organization.payments', array_merge($this->pageData(), [
                'message' => $exception->getMessage(),
            ]), 422);
        }

        if (! $valid) {
            return response()->view('organization.payments', array_merge($this->pageData(), [
                'message' => 'Could not validate the Authorize.net connection.',
            ]), 422);
        }

        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            abort(403);
        }

        $setting = PaymentSetting::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'environment' => $environment,
                'api_login_id' => $credentials->apiLoginId,
                'transaction_key' => $credentials->transactionKey,
                'signature_key' => $credentials->signatureKey,
                'public_client_key' => $credentials->publicClientKey,
                'validated_at' => now(),
            ],
        );

        $this->audit->write('payment_settings.validated', $organizationId, $setting, [
            'environment' => $environment,
            'provider' => 'authorize_net',
        ]);

        return response()->view('organization.payments', array_merge($this->pageData(), [
            'status' => 'Sandbox connection validated. Secrets are stored encrypted and are not shown again.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        $setting = PaymentSetting::query()->first();
        $login = $setting instanceof PaymentSetting ? (string) $setting->api_login_id : '';
        $hint = strlen($login) >= 4 ? substr($login, -4) : null;

        return [
            'title' => 'Payments',
            'environment' => $setting instanceof PaymentSetting ? $setting->environment : 'sandbox',
            'validated' => $setting instanceof PaymentSetting && $setting->validated_at !== null,
            'loginHint' => $hint,
            'publicClientKey' => $setting instanceof PaymentSetting ? $setting->public_client_key : null,
            'liveEnabled' => config('payments.authorize_net_live') === true,
        ];
    }
}
