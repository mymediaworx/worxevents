<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use App\Enums\FeeLedgerKind;
use App\Models\FeeLedgerEntry;
use App\Models\FeeSnapshot;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes one immutable snapshot per order. A repeated call with the same
 * basis returns the original snapshot and does not add another assessed row.
 * A repeated call with a different basis is refused. Later fee-rule changes
 * do not rewrite the snapshot.
 */
final class BookingFeeRecorder
{
    public function __construct(
        private readonly BookingFeeCalculator $calculator,
        private readonly FeeRuleResolver $rules,
        private readonly OrganizationManager $organizations,
    ) {}

    public function record(string $organizationId, string $orderKey, FeeBasis $basis, DateTimeInterface $asOf): FeeSnapshot
    {
        $this->assertContext($organizationId);

        return DB::transaction(function () use ($organizationId, $orderKey, $basis, $asOf): FeeSnapshot {
            $existing = FeeSnapshot::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->where('order_key', $orderKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof FeeSnapshot) {
                if (! $this->sameBasis($existing, $basis)) {
                    throw new IdempotencyConflict('This order already has a fee snapshot for a different basis.');
                }

                return $existing;
            }

            $rule = $this->rules->effective($organizationId, $asOf);
            $quote = $this->calculator->quote($rule->toData(), $basis);

            $snapshot = FeeSnapshot::query()->create([
                'organization_id' => $organizationId,
                'order_key' => $orderKey,
                'fee_rule_id' => $rule->id,
                'currency' => $rule->currency,
                'ticket_subtotal_cents' => $basis->ticketSubtotalCents,
                'discount_cents' => $basis->discountCents,
                'eligible_cents' => $quote->eligibleCents,
                'merchandise_cents' => $basis->merchandiseCents,
                'donation_cents' => $basis->donationCents,
                'add_on_cents' => $basis->addOnCents,
                'tax_cents' => $basis->taxCents,
                'fee_cents' => $quote->feeCents,
                'percent_bps' => $rule->percent_bps,
                'minimum_cents' => $rule->minimum_cents,
                'maximum_cents' => $rule->maximum_cents,
                'rounding' => 'half_up',
                'created_at' => now(),
            ]);

            $this->postLedger(
                $organizationId,
                $orderKey,
                FeeLedgerKind::Assessed,
                $quote->feeCents,
                $rule->currency,
                $organizationId.':'.$orderKey.':assessed',
                $snapshot->id,
            );

            return $snapshot;
        });
    }

    public function postLedger(
        string $organizationId,
        string $orderKey,
        FeeLedgerKind $kind,
        int $amountCents,
        string $currency,
        string $idempotencyKey,
        ?string $snapshotId = null,
    ): FeeLedgerEntry {
        $this->assertContext($organizationId);

        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Ledger amounts cannot be negative.');
        }

        return DB::transaction(function () use ($organizationId, $orderKey, $kind, $amountCents, $currency, $idempotencyKey, $snapshotId): FeeLedgerEntry {
            $existing = FeeLedgerEntry::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof FeeLedgerEntry) {
                if ($existing->organization_id !== $organizationId
                    || $existing->order_key !== $orderKey
                    || $existing->kind !== $kind
                    || $existing->amount_cents !== $amountCents) {
                    throw new IdempotencyConflict('This ledger key was already used for a different entry.');
                }

                return $existing;
            }

            return FeeLedgerEntry::query()->create([
                'organization_id' => $organizationId,
                'fee_snapshot_id' => $snapshotId,
                'order_key' => $orderKey,
                'kind' => $kind,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now(),
                'metadata' => [],
                'created_at' => now(),
            ]);
        });
    }

    private function assertContext(string $organizationId): void
    {
        if ($this->organizations->id() !== $organizationId) {
            throw new \RuntimeException('Fee records can only be written for the active organization.');
        }
    }

    private function sameBasis(FeeSnapshot $snapshot, FeeBasis $basis): bool
    {
        return $snapshot->ticket_subtotal_cents === $basis->ticketSubtotalCents
            && $snapshot->discount_cents === $basis->discountCents
            && $snapshot->merchandise_cents === $basis->merchandiseCents
            && $snapshot->donation_cents === $basis->donationCents
            && $snapshot->add_on_cents === $basis->addOnCents
            && $snapshot->tax_cents === $basis->taxCents;
    }
}
