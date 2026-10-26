<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use App\Enums\FeeLedgerKind;
use App\Models\FeeLedgerEntry;
use App\Models\Order;
use App\Models\RemittanceInvoice;
use App\Tenancy\OrganizationManager;

final class FinancialReport
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    /**
     * @return array{assessed: int, collected: int, owed: int, remitted: int, outstanding: int, open_invoices: int}
     */
    public function totals(): array
    {
        $organizationId = $this->requireOrganization();
        $totals = [
            FeeLedgerKind::Assessed->value => 0,
            FeeLedgerKind::Collected->value => 0,
            FeeLedgerKind::Owed->value => 0,
            FeeLedgerKind::Remitted->value => 0,
        ];

        $rows = FeeLedgerEntry::query()->where('organization_id', $organizationId)->get(['kind', 'amount_cents']);

        foreach ($rows as $row) {
            $totals[$row->kind->value] += $row->amount_cents;
        }

        $open = 0;
        $invoices = RemittanceInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->get(['amount_cents']);

        foreach ($invoices as $invoice) {
            $open += $invoice->amount_cents;
        }

        return [
            'assessed' => $totals[FeeLedgerKind::Assessed->value],
            'collected' => $totals[FeeLedgerKind::Collected->value],
            'owed' => $totals[FeeLedgerKind::Owed->value],
            'remitted' => $totals[FeeLedgerKind::Remitted->value],
            'outstanding' => $totals[FeeLedgerKind::Owed->value] - $totals[FeeLedgerKind::Remitted->value],
            'open_invoices' => $open,
        ];
    }

    public function csv(): string
    {
        $organizationId = $this->requireOrganization();
        $orders = Order::query()->latest()->limit(100)->get();
        $ledger = [];

        $entries = FeeLedgerEntry::query()->where('organization_id', $organizationId)->get(['order_key', 'kind', 'amount_cents']);

        foreach ($entries as $entry) {
            $ledger[$entry->order_key][$entry->kind->value] = ($ledger[$entry->order_key][$entry->kind->value] ?? 0) + $entry->amount_cents;
        }

        $lines = ['reference,order_id,status,payment_status,ticket_subtotal_cents,discount_cents,fee_cents,total_cents,assessed_cents,collected_cents,owed_cents,remitted_cents'];

        foreach ($orders as $order) {
            $row = $ledger[$order->id] ?? [];
            $lines[] = implode(',', [
                $this->cell($order->reference),
                $this->cell($order->id),
                $this->cell($order->status->value),
                $this->cell($order->payment_status->value),
                (string) $order->ticket_subtotal_cents,
                (string) $order->discount_cents,
                (string) $order->fee_cents,
                (string) $order->total_cents,
                (string) ($row[FeeLedgerKind::Assessed->value] ?? 0),
                (string) ($row[FeeLedgerKind::Collected->value] ?? 0),
                (string) ($row[FeeLedgerKind::Owed->value] ?? 0),
                (string) ($row[FeeLedgerKind::Remitted->value] ?? 0),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function cell(string $value): string
    {
        if (preg_match('/^[=+\-@]/', $value) === 1 || str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function requireOrganization(): string
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            throw new \RuntimeException('Choose an organization before reading financial reports.');
        }

        return $organizationId;
    }
}
