<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMember;
use App\Models\PlatformMember;
use App\Models\User;

final class Access
{
    public function membership(User $user, string $organizationId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active)
            ->with('role.permissions')
            ->first();
    }

    public function allows(User $user, string $organizationId, string $permission): bool
    {
        $membership = $this->membership($user, $organizationId);

        if ($membership === null || $membership->role === null) {
            return false;
        }

        return $membership->role->permissions->contains(
            fn ($item): bool => $item->slug === $permission,
        );
    }

    public function platformMembership(User $user): ?PlatformMember
    {
        return PlatformMember::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active)
            ->with('role.permissions')
            ->first();
    }

    public function platformAllows(User $user, string $permission): bool
    {
        $membership = $this->platformMembership($user);

        if ($membership === null || $membership->role === null) {
            return false;
        }

        if ($membership->role->scope !== 'platform') {
            return false;
        }

        return $membership->role->permissions->contains(
            fn ($item): bool => $item->slug === $permission,
        );
    }
}
