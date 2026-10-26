<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use App\Enums\FeeLedgerKind;
use App\Models\FeeLedgerEntry;
use App\Tenancy\OrganizationManager;

final class FeeLedgerBalances
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    /**
     * @return array{assessed: int, collected: int, owed: int, remitted: int}
     */
    public function forOrder(string $organizationId, string $orderKey): array
    {
        if ($this->organizations->id() !== $organizationId) {
            throw new \RuntimeException('Fee balances can only be read for the active organization.');
        }

        $totals = [
            FeeLedgerKind::Assessed->value => 0,
            FeeLedgerKind::Collected->value => 0,
            FeeLedgerKind::Owed->value => 0,
            FeeLedgerKind::Remitted->value => 0,
        ];

        $rows = FeeLedgerEntry::query()
            ->where('organization_id', $organizationId)
            ->where('order_key', $orderKey)
            ->get(['kind', 'amount_cents']);

        foreach ($rows as $row) {
            $totals[$row->kind->value] += $row->amount_cents;
        }

        return [
            'assessed' => $totals[FeeLedgerKind::Assessed->value],
            'collected' => $totals[FeeLedgerKind::Collected->value],
            'owed' => $totals[FeeLedgerKind::Owed->value],
            'remitted' => $totals[FeeLedgerKind::Remitted->value],
        ];
    }
}
