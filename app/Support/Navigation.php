<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;

final class Navigation
{
    /**
     * @return list<array{label: string, route: string, permission: ?string}>
     */
    public static function organizationLinks(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null],
            ['label' => 'Events', 'route' => 'events', 'permission' => 'events.view'],
            ['label' => 'Box office', 'route' => 'box-office', 'permission' => 'tickets.sell'],
            ['label' => 'Check-in', 'route' => 'check-in', 'permission' => 'checkin.scan'],
            ['label' => 'Finance', 'route' => 'finance', 'permission' => 'finance.view'],
            ['label' => 'Tickets', 'route' => 'tickets', 'permission' => 'orders.view_own'],
            ['label' => 'Team', 'route' => 'team', 'permission' => 'organization.members.view'],
        ];
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    public static function visible(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $organizationId = app(OrganizationManager::class)->id();

        if ($organizationId === null) {
            return [];
        }

        $access = app(Access::class);
        $links = [];

        foreach (self::organizationLinks() as $link) {
            $permission = $link['permission'];

            if ($permission !== null && ! $access->allows($user, $organizationId, $permission)) {
                continue;
            }

            $links[] = [
                'label' => $link['label'],
                'href' => route($link['route']),
            ];
        }

        return $links;
    }

    /**
     * @return list<array{name: string, href: string}>
     */
    public static function workspaces(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $current = app(OrganizationManager::class)->id();

        $memberships = app(OrganizationManager::class)->bypass(function () use ($user) {
            return OrganizationMember::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active)
                ->with(['organization.domains'])
                ->get();
        });

        $links = [];

        foreach ($memberships as $membership) {
            if ($membership->organization_id === $current || $membership->organization === null) {
                continue;
            }

            $domain = $membership->organization->domains
                ->first(fn ($item): bool => $item->verified_at !== null && $item->is_primary);

            if ($domain === null) {
                continue;
            }

            $links[] = [
                'name' => $membership->organization->name,
                'href' => 'https://'.$domain->hostname.'/dashboard',
            ];
        }

        return $links;
    }
}
