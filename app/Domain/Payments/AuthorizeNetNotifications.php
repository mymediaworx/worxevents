<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Catalog\OrderSettlement;
use App\Domain\Refunds\OrganizerRefundWorkflow;
use App\Domain\Refunds\RefundCompletion;
use App\Domain\Refunds\RefundNotConfirmed;
use App\Domain\Tickets\TicketMailer;
use App\Domain\Tickets\TicketPass;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\GatewayNotification;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PaymentSetting;
use App\Models\RefundRequest;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;

/**
 * Verifies an Authorize.net webhook, stores it once, and settles the order
 * or refund it points at. A repeated notification id does nothing.
 */
final class AuthorizeNetNotifications
{
    public function __construct(
        private readonly AuthorizeNetSignature $signatures,
        private readonly AuthorizeNetGateway $gateway,
        private readonly OrderSettlement $settlement,
        private readonly OrganizerRefundWorkflow $refunds,
        private readonly RefundCompletion $completion,
        private readonly TicketMailer $mailer,
        private readonly OrganizationManager $organizations,
    ) {}

    public function handle(Organization $organization, string $rawBody, string $signatureHeader): int
    {
        $this->organizations->set($organization);
        $setting = PaymentSetting::query()->where('organization_id', $organization->id)->first();

        if (! $setting instanceof PaymentSetting || ! $this->signatures->matches($rawBody, $signatureHeader, (string) $setting->signature_key)) {
            return 401;
        }

        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return 400;
        }

        $notificationId = $decoded['notificationId'] ?? null;
        $eventType = $decoded['eventType'] ?? null;
        $payload = $decoded['payload'] ?? null;
        $transactionId = is_array($payload) ? ($payload['id'] ?? null) : null;

        if (! is_string($notificationId) || $notificationId === '' || strlen($notificationId) > 64) {
            return 400;
        }

        if (! is_string($eventType) || $eventType === '') {
            return 400;
        }

        $transaction = is_scalar($transactionId) ? (string) $transactionId : null;

        /** @var array{order: Order, title: string, passes: list<TicketPass>}|null $delivery */
        $delivery = null;

        DB::transaction(function () use ($organization, $notificationId, $eventType, $transaction, $rawBody, &$delivery): void {
            $existing = GatewayNotification::query()
                ->where('notification_id', $notificationId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof GatewayNotification) {
                return;
            }

            $notice = GatewayNotification::query()->create([
                'organization_id' => $organization->id,
                'notification_id' => $notificationId,
                'event_type' => substr($eventType, 0, 120),
                'transaction_id' => $transaction !== null ? substr($transaction, 0, 64) : null,
                'payload_sha256' => hash('sha256', $rawBody),
            ]);

            if ($transaction !== null && $transaction !== '') {
                $delivery = $this->apply($organization->id, $eventType, $transaction);
            }

            $notice->forceFill(['processed_at' => now()])->save();
        });

        if ($delivery !== null) {
            $this->mailer->send($delivery['order'], $delivery['title'], $delivery['passes']);
        }

        return 200;
    }

    /**
     * @return array{order: Order, title: string, passes: list<TicketPass>}|null
     */
    private function apply(string $organizationId, string $eventType, string $transactionId): ?array
    {
        $result = $this->gateway->lookup($organizationId, $transactionId);

        if (! $result->accepted || $result->status !== 'approved' || $result->reference === null) {
            return null;
        }

        if (str_contains($eventType, 'refund')) {
            $this->confirmRefund($result->reference);

            return null;
        }

        $invoice = $result->invoiceNumber;

        if ($invoice === null || $invoice === '') {
            return null;
        }

        $order = Order::query()->where('reference', $invoice)->first();

        if (! $order instanceof Order || $order->status !== OrderStatus::Pending) {
            return null;
        }

        $settled = $this->settlement->approve($order, $result->reference, $result->accountLastFour);

        if (! $settled['created']) {
            return null;
        }

        $eventId = $settled['order']->attendees()->value('event_id');
        $event = Event::query()->whereKey($eventId)->first();
        $title = $event instanceof Event ? $event->title : 'Event';

        return [
            'order' => $settled['order'],
            'title' => $title,
            'passes' => $settled['passes'],
        ];
    }

    private function confirmRefund(string $reference): void
    {
        $refund = RefundRequest::query()
            ->where('provider_reference', $reference)
            ->where('status', RefundStatus::Submitted->value)
            ->first();

        if (! $refund instanceof RefundRequest) {
            return;
        }

        try {
            $confirmed = $this->refunds->confirm($refund, $reference, true);
        } catch (RefundNotConfirmed) {
            return;
        }

        $this->completion->apply($confirmed);
    }
}
