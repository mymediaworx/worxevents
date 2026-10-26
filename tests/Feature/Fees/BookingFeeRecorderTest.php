<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Domain\Fees\BookingFeeRecorder;
use App\Domain\Fees\FeeBasis;
use App\Domain\Fees\FeeLedgerBalances;
use App\Domain\Fees\IdempotencyConflict;
use App\Enums\FeeLedgerKind;
use App\Models\FeeLedgerEntry;
use App\Models\FeeRule;
use App\Models\FeeSnapshot;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class BookingFeeRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshots_are_idempotent_and_rules_apply_to_new_orders_only(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $recorder = app(BookingFeeRecorder::class);
        $asOf = now()->parse('2026-06-01 12:00:00');

        $first = $recorder->record(
            $workspace['organization']->id,
            'order-50',
            new FeeBasis(5000),
            $asOf,
        );
        $again = $recorder->record(
            $workspace['organization']->id,
            'order-50',
            new FeeBasis(5000),
            $asOf,
        );

        $this->assertSame($first->id, $again->id);
        $this->assertSame(150, $first->fee_cents);
        $this->assertSame(1, FeeSnapshot::query()->count());
        $this->assertSame(1, FeeLedgerEntry::query()->where('kind', FeeLedgerKind::Assessed)->count());

        FeeRule::query()->create([
            'organization_id' => null,
            'percent_bps' => 1000,
            'minimum_cents' => 0,
            'maximum_cents' => 100000,
            'currency' => 'USD',
            'effective_at' => '2026-07-01 00:00:00',
            'note' => 'Later rule',
        ]);

        $still = $recorder->record(
            $workspace['organization']->id,
            'order-50',
            new FeeBasis(5000),
            now()->parse('2026-08-01 12:00:00'),
        );
        $this->assertSame(150, $still->fee_cents);

        $next = $recorder->record(
            $workspace['organization']->id,
            'order-new',
            new FeeBasis(10000),
            now()->parse('2026-08-01 12:00:00'),
        );

        $this->assertSame(1000, $next->fee_cents);
        $this->expectException(RuntimeException::class);
        $first->fee_cents = 1;
        $first->save();
    }

    public function test_a_different_basis_for_the_same_order_is_refused(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);

        $recorder = app(BookingFeeRecorder::class);
        $recorder->record($workspace['organization']->id, 'order-1', new FeeBasis(5000), now());

        $this->expectException(IdempotencyConflict::class);
        $recorder->record($workspace['organization']->id, 'order-1', new FeeBasis(10000), now());
    }

    public function test_ledger_kinds_stay_separate_and_idempotent(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $recorder = app(BookingFeeRecorder::class);
        $organizationId = $workspace['organization']->id;

        $recorder->record($organizationId, 'order-1', new FeeBasis(10000), now());
        $recorder->postLedger($organizationId, 'order-1', FeeLedgerKind::Owed, 300, 'USD', $organizationId.':order-1:owed');
        $recorder->postLedger($organizationId, 'order-1', FeeLedgerKind::Owed, 300, 'USD', $organizationId.':order-1:owed');
        $recorder->postLedger($organizationId, 'order-1', FeeLedgerKind::Remitted, 300, 'USD', $organizationId.':order-1:remitted');

        $balances = app(FeeLedgerBalances::class)->forOrder($organizationId, 'order-1');

        $this->assertSame([
            'assessed' => 300,
            'collected' => 0,
            'owed' => 300,
            'remitted' => 300,
        ], $balances);
    }

    public function test_another_organization_cannot_read_the_snapshot(): void
    {
        $alpha = $this->workspace('alpha.test');
        $beta = $this->workspace('beta.test');
        app(OrganizationManager::class)->set($alpha['organization']);
        app(BookingFeeRecorder::class)->record($alpha['organization']->id, 'order-1', new FeeBasis(5000), now());

        app(OrganizationManager::class)->set($beta['organization']);

        $this->assertSame(0, FeeSnapshot::query()->count());
    }
}
