<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Fees\MoneyFormat;
use Illuminate\Support\Facades\Http;

/**
 * Authorize.net JSON API. Sandbox only, unless the platform live flag is on.
 * Card numbers are never accepted. Charges use an Accept.js opaque nonce.
 */
final class HttpAuthorizeNetClient implements AuthorizeNetClient
{
    public function authenticate(AuthorizeNetCredentials $credentials): bool
    {
        $payload = $this->post($credentials, [
            'authenticateTestRequest' => [
                'merchantAuthentication' => $this->merchant($credentials),
            ],
        ]);

        return ($payload['messages']['resultCode'] ?? null) === 'Ok';
    }

    public function charge(AuthorizeNetCredentials $credentials, ChargeInstruction $instruction): ProviderResult
    {
        $this->assertOpaque($instruction->dataDescriptor, $instruction->dataValue);

        $payload = $this->post($credentials, [
            'createTransactionRequest' => [
                'merchantAuthentication' => $this->merchant($credentials),
                'refId' => substr($instruction->invoiceNumber, 0, 20),
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => MoneyFormat::centsToDollars($instruction->amountCents),
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => $instruction->dataDescriptor,
                            'dataValue' => $instruction->dataValue,
                        ],
                    ],
                    'order' => [
                        'invoiceNumber' => substr($instruction->invoiceNumber, 0, 20),
                        'description' => substr($instruction->description, 0, 255),
                    ],
                ],
            ],
        ]);

        return $this->transactionResult($payload);
    }

    public function refund(
        AuthorizeNetCredentials $credentials,
        string $transactionId,
        RefundInstruction $instruction,
        ?string $cardLastFour,
    ): ProviderResult {
        $lastFour = $cardLastFour;

        if ($lastFour === null || strlen($lastFour) !== 4) {
            $existing = $this->fetch($credentials, $transactionId);
            $lastFour = $existing->accountLastFour;
        }

        if ($lastFour === null || strlen($lastFour) !== 4) {
            return new ProviderResult(false, false, null, 'The original card last four is not available.', 'declined');
        }

        $payload = $this->post($credentials, [
            'createTransactionRequest' => [
                'merchantAuthentication' => $this->merchant($credentials),
                'refId' => substr($instruction->idempotencyKey, 0, 20),
                'transactionRequest' => [
                    'transactionType' => 'refundTransaction',
                    'amount' => MoneyFormat::centsToDollars($instruction->amountCents),
                    'payment' => [
                        'creditCard' => [
                            'cardNumber' => $lastFour,
                            'expirationDate' => 'XXXX',
                        ],
                    ],
                    'refTransId' => $transactionId,
                ],
            ],
        ]);

        return $this->transactionResult($payload);
    }

    public function fetch(AuthorizeNetCredentials $credentials, string $transactionId): ProviderResult
    {
        $payload = $this->post($credentials, [
            'getTransactionDetailsRequest' => [
                'merchantAuthentication' => $this->merchant($credentials),
                'transId' => $transactionId,
            ],
        ]);

        $transaction = $payload['transaction'] ?? null;

        if (! is_array($transaction)) {
            return new ProviderResult(false, false, null, 'Transaction was not found.', 'declined');
        }

        $responseCode = (string) ($transaction['responseCode'] ?? '');
        $statusName = (string) ($transaction['transactionStatus'] ?? '');
        $approved = $responseCode === '1' || in_array($statusName, ['capturedPendingSettlement', 'settledSuccessfully'], true);
        $invoice = $transaction['order']['invoiceNumber'] ?? null;
        $account = (string) ($transaction['payment']['creditCard']['cardNumber'] ?? '');
        $lastFour = strlen($account) >= 4 ? substr($account, -4) : null;

        return new ProviderResult(
            accepted: $approved,
            completed: false,
            reference: $approved ? $transactionId : null,
            detail: $approved ? 'Approved.' : 'Not approved.',
            status: $approved ? 'approved' : 'declined',
            accountLastFour: $lastFour,
            invoiceNumber: is_string($invoice) ? $invoice : null,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(AuthorizeNetCredentials $credentials, array $body): array
    {
        $this->assertEnvironment($credentials->environment);

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->endpoint($credentials->environment), $body);

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? '';
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new GatewayDisabled('Authorize.net did not return a readable response.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transactionResult(array $payload): ProviderResult
    {
        $transaction = $payload['transactionResponse'] ?? null;

        if (! is_array($transaction)) {
            return new ProviderResult(false, false, null, 'Authorize.net did not return a transaction.', 'error');
        }

        $code = (string) ($transaction['responseCode'] ?? '');
        $transId = $transaction['transId'] ?? null;
        $reference = is_string($transId) && $transId !== '' && $transId !== '0' ? $transId : null;
        $account = (string) ($transaction['accountNumber'] ?? '');
        $lastFour = strlen($account) >= 4 ? substr($account, -4) : null;

        if ($code === '1' && $reference !== null) {
            return new ProviderResult(true, false, $reference, 'Approved.', 'approved', $lastFour);
        }

        $status = $code === '2' ? 'declined' : 'error';

        return new ProviderResult(false, false, null, 'The processor did not approve the transaction.', $status, $lastFour);
    }

    /**
     * @return array{name: string, transactionKey: string}
     */
    private function merchant(AuthorizeNetCredentials $credentials): array
    {
        return [
            'name' => $credentials->apiLoginId,
            'transactionKey' => $credentials->transactionKey,
        ];
    }

    private function endpoint(string $environment): string
    {
        if ($environment === 'live' && config('payments.authorize_net_live') === true) {
            return 'https://api.authorize.net/xml/v1/request.api';
        }

        return 'https://apitest.authorize.net/xml/v1/request.api';
    }

    private function assertEnvironment(string $environment): void
    {
        if ($environment === 'sandbox') {
            return;
        }

        if ($environment === 'live' && config('payments.authorize_net_live') === true) {
            return;
        }

        throw new GatewayDisabled('Live Authorize.net mode is disabled until the platform enables it.');
    }

    private function assertOpaque(string $descriptor, string $value): void
    {
        $allowed = ['COMMON.ACCEPT.INAPP.PAYMENT', 'COMMON.ACCEPT.PAYMENT'];

        if (! in_array($descriptor, $allowed, true) || strlen($value) < 10) {
            throw new GatewayDisabled('Card data is never accepted. Submit an Accept.js payment nonce.');
        }
    }
}
