<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\OrganizationStatus;
use App\Models\FeeRule;
use App\Models\FeeSnapshot;
use App\Models\OrganizationMember;
use App\Policies\FeeSnapshotPolicy;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_without_an_organization_match_nothing(): void
    {
        $this->workspace('alpha.test');
        $this->workspace('beta.test');

        app(OrganizationManager::class)->forget();

        $this->assertSame(0, OrganizationMember::query()->count());
        $this->assertSame(2, OrganizationMember::query()->withoutGlobalScope(OrganizationScope::class)->count());
    }

    public function test_the_active_organization_cannot_see_another_organizations_members(): void
    {
        $alpha = $this->workspace('alpha.test');
        $beta = $this->workspace('beta.test');

        app(OrganizationManager::class)->set($alpha['organization']);

        $this->assertSame(1, OrganizationMember::query()->count());
        $this->assertNull(OrganizationMember::query()->find($beta['membership']->id));
    }

    public function test_a_signed_in_member_cannot_open_another_organization(): void
    {
        $alpha = $this->workspace('alpha.test');
        $beta = $this->workspace('beta.test');

        $this->actingAs($alpha['user'])
            ->get($this->hostUrl('beta.test', '/team'))
            ->assertForbidden();

        $this->actingAs($alpha['user'])
            ->get($this->hostUrl('alpha.test', '/dashboard'), [
                'X-Organization-Id' => $beta['organization']->id,
            ])->assertForbidden();

        $this->actingAs($alpha['user'])
            ->get($this->hostUrl('alpha.test', '/dashboard'))
            ->assertOk();
    }

    public function test_a_platform_administrator_without_membership_cannot_use_organization_routes(): void
    {
        $this->workspace('alpha.test');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get($this->hostUrl('alpha.test', '/dashboard'))
            ->assertForbidden();
    }

    public function test_an_organization_member_cannot_open_the_platform(): void
    {
        $alpha = $this->workspace('alpha.test');

        $this->actingAs($alpha['user'])
            ->get($this->hostUrl('app.worxevents.test', '/platform'))
            ->assertForbidden();
    }

    public function test_a_policy_denies_a_snapshot_from_another_organization(): void
    {
        $alpha = $this->workspace('alpha.test');
        $beta = $this->workspace('beta.test');

        app(OrganizationManager::class)->set($beta['organization']);
        $foreign = FeeSnapshot::query()->create([
            'organization_id' => $beta['organization']->id,
            'order_key' => 'order-1',
            'fee_rule_id' => FeeRule::query()->firstOrFail()->id,
            'currency' => 'USD',
            'ticket_subtotal_cents' => 5000,
            'discount_cents' => 0,
            'eligible_cents' => 5000,
            'merchandise_cents' => 0,
            'donation_cents' => 0,
            'add_on_cents' => 0,
            'tax_cents' => 0,
            'fee_cents' => 150,
            'percent_bps' => 300,
            'minimum_cents' => 100,
            'maximum_cents' => 500,
            'rounding' => 'half_up',
            'created_at' => now(),
        ]);

        app(OrganizationManager::class)->set($alpha['organization']);

        $this->assertFalse(app(FeeSnapshotPolicy::class)->view($alpha['user'], $foreign));
        $this->assertSame(0, FeeSnapshot::query()->count());
    }

    public function test_a_suspended_organization_is_unavailable(): void
    {
        $workspace = $this->workspace('alpha.test');
        $workspace['organization']->forceFill(['status' => OrganizationStatus::Suspended])->save();

        $this->get($this->hostUrl('alpha.test', '/'))->assertForbidden();
    }

    public function test_an_unknown_host_is_not_found(): void
    {
        $this->get('http://missing.test/')->assertNotFound();
    }
}
