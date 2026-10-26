<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformMember;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkspacePasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class OrganizationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent_and_does_not_rotate_a_password(): void
    {
        Notification::fake();

        config([
            'worxevents.apex' => 'worxevents.test',
            'organizations' => [[
                'slug' => 'harbor',
                'name' => 'Harbor Events',
                'owner_email' => 'owner@example.com',
                'timezone' => 'America/Chicago',
                'hostname' => 'harbor.worxevents.test',
            ]],
        ]);

        $this->artisan('worxevents:organization:sync')
            ->expectsOutputToContain('organization_host=harbor.worxevents.test')
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $hash = $user->password;

        $this->artisan('worxevents:organization:sync')
            ->expectsOutputToContain('created=none')
            ->assertSuccessful();

        $this->assertSame(1, Organization::query()->count());
        $this->assertSame($hash, $user->fresh()->password);
        Notification::assertSentTimes(WorkspacePasswordReset::class, 1);

        $this->get('http://harbor.worxevents.test/')->assertOk()->assertSee('Harbor Events');
    }

    public function test_hosting_check_prints_no_database_password(): void
    {
        $this->artisan('worxevents:hosting-check')
            ->expectsOutputToContain('queue_table=background_jobs')
            ->doesntExpectOutputToContain('DB_PASSWORD')
            ->assertSuccessful();
    }

    public function test_pagination_is_capped_at_100(): void
    {
        $workspace = $this->workspace('alpha.test', 'org_owner');

        for ($i = 0; $i < 100; $i++) {
            $user = User::factory()->create();
            OrganizationMember::factory()->create([
                'organization_id' => $workspace['organization']->id,
                'user_id' => $user->id,
                'role_id' => Role::query()->where('slug', 'check_in_staff')->firstOrFail()->id,
            ]);
        }

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/team?per_page=500'))
            ->assertOk()
            ->assertSee('data-member-count="100"', false);
    }

    public function test_platform_admin_command_is_idempotent_and_does_not_print_a_password(): void
    {
        Notification::fake();

        $this->artisan('worxevents:platform-admin', ['email' => 'not-an-email'])
            ->expectsOutputToContain('email=invalid')
            ->assertFailed();

        $this->artisan('worxevents:platform-admin', ['email' => 'admin@example.com'])
            ->expectsOutputToContain('reset_link_sent=yes')
            ->expectsOutputToContain('platform_host=app.worxevents.test')
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $hash = $user->password;

        $this->artisan('worxevents:platform-admin', ['email' => 'admin@example.com'])
            ->expectsOutputToContain('created=none')
            ->expectsOutputToContain('reset_link_sent=no')
            ->assertSuccessful();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(1, PlatformMember::query()->where('user_id', $user->id)->count());
        Notification::assertSentTimes(WorkspacePasswordReset::class, 1);
    }
}
