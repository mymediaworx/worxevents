<?php

declare(strict_types=1);

namespace App\Domain\Refunds;

use App\Domain\Payments\PaymentGatewayManager;
use App\Domain\Payments\RefundInstruction;
use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;

/**
 * The organizer decides. The provider confirms. This service never marks a
 * refund complete from the decision or from the submit call.
 */
final class OrganizerRefundWorkflow
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrganizationManager $organizations,
        private readonly AuditLogger $audit,
    ) {}

    public function request(User $actor, string $orderKey, int $amountCents, string $note): RefundRequest
    {
        $organizationId = $this->requireOrganization();

        if ($amountCents < 1) {
            throw new \InvalidArgumentException('Refund amount must be at least one cent.');
        }

        $refund = RefundRequest::query()->create([
            'organization_id' => $organizationId,
            'order_key' => $orderKey,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'status' => RefundStatus::Requested,
            'decided_by_user_id' => $actor->id,
            'decision_note' => $note,
            'provider' => $this->gateways->currentName(),
            'provider_reference' => null,
            'completed_at' => null,
        ]);

        $this->audit->write(
            action: 'refund.requested',
            organizationId: $organizationId,
            subject: $refund,
            properties: [
                'order_key' => $orderKey,
                'amount_cents' => $amountCents,
                'provider' => $refund->provider,
            ],
        );

        return $refund;
    }

    public function submit(RefundRequest $refund): RefundRequest
    {
        $organizationId = $this->requireOrganization();

        if ($refund->organization_id !== $organizationId) {
            throw new RefundNotConfirmed('That refund belongs to another organization.');
        }

        return DB::transaction(function () use ($refund, $organizationId): RefundRequest {
            $locked = RefundRequest::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== RefundStatus::Requested) {
                throw new RefundNotConfirmed('Only a requested refund can be submitted.');
            }

            $result = $this->gateways->gateway($locked->provider)->refund(new RefundInstruction(
                organizationId: $organizationId,
                orderKey: $locked->order_key,
                amountCents: $locked->amount_cents,
                currency: $locked->currency,
                idempotencyKey: $locked->id,
            ));

            $locked->status = $result->accepted ? RefundStatus::Submitted : RefundStatus::Failed;
            $locked->provider_reference = $result->reference;
            $locked->completed_at = null;
            $locked->save();

            $this->audit->write(
                action: 'refund.submitted',
                organizationId: $organizationId,
                subject: $locked,
                properties: [
                    'provider' => $locked->provider,
                    'accepted' => $result->accepted,
                ],
            );

            return $locked;
        });
    }

    public function confirm(RefundRequest $refund, string $providerReference, bool $succeeded): RefundRequest
    {
        $organizationId = $this->requireOrganization();

        if ($refund->organization_id !== $organizationId) {
            throw new RefundNotConfirmed('That refund belongs to another organization.');
        }

        return DB::transaction(function () use ($refund, $providerReference, $succeeded, $organizationId): RefundRequest {
            $locked = RefundRequest::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== RefundStatus::Submitted) {
                throw new RefundNotConfirmed('Only a submitted refund can be confirmed.');
            }

            $stored = (string) $locked->provider_reference;

            if ($locked->provider_reference === null || strlen($stored) !== strlen($providerReference) || ! hash_equals($stored, $providerReference)) {
                throw new RefundNotConfirmed('Provider reference does not match.');
            }

            if (! $succeeded) {
                $locked->status = RefundStatus::Failed;
                $locked->save();

                return $locked;
            }

            $locked->status = RefundStatus::Completed;
            $locked->completed_at = now();
            $locked->save();

            $this->audit->write(
                action: 'refund.completed',
                organizationId: $organizationId,
                subject: $locked,
                properties: [
                    'provider' => $locked->provider,
                ],
            );

            return $locked;
        });
    }

    private function requireOrganization(): string
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            throw new RefundNotConfirmed('Choose an organization before recording a refund.');
        }

        return $organizationId;
    }
}
