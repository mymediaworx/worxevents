<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Authorization\RoleCatalog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RoleGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_matrix_matches_the_catalog(): void
    {
        foreach (RoleCatalog::roles() as $slug => $role) {
            $model = Role::query()->where('slug', $slug)->firstOrFail();
            $granted = $model->permissions->pluck('slug')->sort()->values()->all();
            $expected = collect($role['permissions'])->sort()->values()->all();

            $this->assertSame($expected, $granted, $slug);
            $this->assertSame($role['scope'], $model->scope);
        }
    }

    #[DataProvider('pages')]
    public function test_section_permissions(string $role, string $path, int $status): void
    {
        $workspace = $this->workspace('alpha.test', $role);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', $path))
            ->assertStatus($status);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function pages(): array
    {
        return [
            'buyer tickets' => ['buyer', '/tickets', 200],
            'buyer team' => ['buyer', '/team', 403],
            'buyer finance' => ['buyer', '/finance', 403],
            'check-in door' => ['check_in_staff', '/check-in', 200],
            'check-in box office' => ['check_in_staff', '/box-office', 403],
            'box office' => ['box_office', '/box-office', 200],
            'box office finance' => ['box_office', '/finance', 403],
            'event manager' => ['event_manager', '/events', 200],
            'event manager team' => ['event_manager', '/team', 403],
            'finance' => ['finance', '/finance', 200],
            'admin team' => ['org_admin', '/team', 200],
            'admin finance view' => ['org_admin', '/finance', 200],
            'owner finance' => ['org_owner', '/finance', 200],
        ];
    }

    public function test_only_owner_and_finance_can_request_a_refund(): void
    {
        $admin = $this->workspace('alpha.test', 'org_admin');

        $this->actingAs($admin['user'])->post($this->hostUrl('alpha.test', '/finance/refunds'), [
            'order_key' => 'order-1',
            'amount' => '10.00',
            'note' => 'Customer asked.',
        ])->assertForbidden();

        $finance = $this->workspace('beta.test', 'finance');

        $this->actingAs($finance['user'])->post($this->hostUrl('beta.test', '/finance/refunds'), [
            'order_key' => 'order-1',
            'amount' => '10.00',
            'note' => 'Customer asked.',
        ])->assertOk();

        $this->assertDatabaseHas('refund_requests', [
            'organization_id' => $finance['organization']->id,
            'status' => 'requested',
        ]);
    }

    public function test_support_can_view_fee_rules_but_cannot_write_them(): void
    {
        $support = $this->platformAdmin('platform_support_admin');

        $this->actingAs($support)
            ->get($this->hostUrl('app.worxevents.test', '/platform/fees'))
            ->assertOk()
            ->assertDontSee('Save rule');

        $this->actingAs($support)
            ->post($this->hostUrl('app.worxevents.test', '/platform/fees'), [
                'percent' => '3',
                'minimum' => '1',
                'maximum' => '5',
                'effective_on' => '2026-12-01',
            ])->assertForbidden();
    }
}
