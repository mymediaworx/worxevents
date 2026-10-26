<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use App\Enums\FeeLedgerKind;
use App\Models\FeeLedgerEntry;
use App\Models\FeeSnapshot;
use App\Models\RemittanceInvoice;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Authorize.net has no split. Outstanding booking fees are invoiced, then
 * marked remitted when that invoice is paid. Cadence stays manual (decision D11).
 */
final class RemittanceService
{
    public function __construct(
        private readonly BookingFeeRecorder $fees,
        private readonly OrganizationManager $organizations,
        private readonly AuditLogger $audit,
    ) {}

    public function open(): RemittanceInvoice
    {
        $organizationId = $this->requireOrganization();

        return DB::transaction(function () use ($organizationId): RemittanceInvoice {
            $outstanding = $this->outstanding($organizationId);

            if ($outstanding === []) {
                throw new InvalidArgumentException('There is no outstanding booking fee to invoice.');
            }

            $amount = 0;

            foreach ($outstanding as $cents) {
                $amount += $cents;
            }

            $invoice = RemittanceInvoice::query()->create([
                'organization_id' => $organizationId,
                'amount_cents' => $amount,
                'currency' => 'USD',
                'status' => 'open',
                'reference' => $this->reference(),
                'order_keys' => array_keys($outstanding),
            ]);

            $this->audit->write('remittance.opened', $organizationId, $invoice, [
                'reference' => $invoice->reference,
                'amount_cents' => $amount,
            ]);

            return $invoice;
        });
    }

    public function markPaid(RemittanceInvoice $invoice, string $paymentReference): RemittanceInvoice
    {
        $organizationId = $this->requireOrganization();

        if ($invoice->organization_id !== $organizationId) {
            throw new InvalidArgumentException('That invoice belongs to another organization.');
        }

        $paymentReference = trim($paymentReference);

        if ($paymentReference === '' || strlen($paymentReference) > 64) {
            throw new InvalidArgumentException('Enter the payment reference for this invoice.');
        }

        return DB::transaction(function () use ($invoice, $paymentReference, $organizationId): RemittanceInvoice {
            $locked = RemittanceInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            if (! $locked instanceof RemittanceInvoice) {
                throw new InvalidArgumentException('That invoice is not available.');
            }

            if ($locked->status === 'paid') {
                $stored = (string) $locked->payment_reference;

                if ($locked->payment_reference !== null && strlen($stored) === strlen($paymentReference) && hash_equals($stored, $paymentReference)) {
                    return $locked;
                }

                throw new IdempotencyConflict('This invoice was already paid under a different reference.');
            }

            foreach ($locked->order_keys as $orderKey) {
                if (! is_string($orderKey)) {
                    continue;
                }

                $owed = $this->owed($organizationId, $orderKey);

                if ($owed < 1) {
                    continue;
                }

                $snapshotId = FeeSnapshot::query()
                    ->where('organization_id', $organizationId)
                    ->where('order_key', $orderKey)
                    ->value('id');

                $this->fees->postLedger(
                    $organizationId,
                    $orderKey,
                    FeeLedgerKind::Remitted,
                    $owed,
                    'USD',
                    $organizationId.':'.$orderKey.':remitted',
                    is_string($snapshotId) ? $snapshotId : null,
                );
            }

            $locked->forceFill([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ])->save();

            $this->audit->write('remittance.paid', $organizationId, $locked, [
                'reference' => $locked->reference,
                'amount_cents' => $locked->amount_cents,
            ]);

            return $locked;
        });
    }

    /**
     * @return array<string, int>
     */
    public function outstanding(string $organizationId): array
    {
        $owed = [];
        $remitted = [];
        $rows = FeeLedgerEntry::query()->where('organization_id', $organizationId)->get(['order_key', 'kind', 'amount_cents']);

        foreach ($rows as $row) {
            if ($row->kind === FeeLedgerKind::Owed) {
                $owed[$row->order_key] = ($owed[$row->order_key] ?? 0) + $row->amount_cents;
            }

            if ($row->kind === FeeLedgerKind::Remitted) {
                $remitted[$row->order_key] = ($remitted[$row->order_key] ?? 0) + $row->amount_cents;
            }
        }

        $openKeys = [];
        $invoices = RemittanceInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->get(['order_keys']);

        foreach ($invoices as $invoice) {
            foreach ($invoice->order_keys as $key) {
                if (is_string($key)) {
                    $openKeys[$key] = true;
                }
            }
        }

        $outstanding = [];

        foreach ($owed as $orderKey => $amount) {
            $left = $amount - ($remitted[$orderKey] ?? 0);

            if ($left > 0 && ! isset($openKeys[$orderKey])) {
                $outstanding[$orderKey] = $left;
            }
        }

        return $outstanding;
    }

    private function owed(string $organizationId, string $orderKey): int
    {
        $amount = 0;
        $rows = FeeLedgerEntry::query()
            ->where('organization_id', $organizationId)
            ->where('order_key', $orderKey)
            ->where('kind', FeeLedgerKind::Owed->value)
            ->get(['amount_cents']);

        foreach ($rows as $row) {
            $amount += $row->amount_cents;
        }

        return $amount;
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'INV'.strtoupper(Str::random(8));
            $taken = RemittanceInvoice::query()->where('reference', $reference)->exists();

            if (! $taken) {
                return $reference;
            }
        }

        throw new InvalidArgumentException('Could not assign an invoice reference.');
    }

    private function requireOrganization(): string
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            throw new InvalidArgumentException('Choose an organization before invoicing fees.');
        }

        return $organizationId;
    }
}
