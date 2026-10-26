<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\OrganizationMember;
use App\Models\PlatformMember;
use App\Models\Role;
use App\Models\User;

trait InteractsWithOrganizations
{
    /**
     * @return array{organization: Organization, user: User, membership: OrganizationMember, host: string}
     */
    protected function workspace(string $host, string $role = 'org_owner', ?User $user = null): array
    {
        $organization = Organization::factory()->create([
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationDomain::factory()->create([
            'organization_id' => $organization->id,
            'hostname' => $host,
            'verified_at' => now(),
            'is_primary' => true,
        ]);

        $user ??= User::factory()->create();
        $roleModel = Role::query()->where('slug', $role)->firstOrFail();

        $membership = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $roleModel->id,
        ]);

        return [
            'organization' => $organization,
            'user' => $user,
            'membership' => $membership,
            'host' => $host,
        ];
    }

    protected function platformAdmin(string $role = 'platform_super_admin'): User
    {
        $user = User::factory()->create();

        PlatformMember::factory()->create([
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $role)->firstOrFail()->id,
        ]);

        return $user;
    }

    protected function hostUrl(string $host, string $path): string
    {
        return 'http://'.$host.$path;
    }
}
