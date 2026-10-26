<?php

declare(strict_types=1);

namespace App\Authorization;

use InvalidArgumentException;

/**
 * Canonical role and permission matrix for Phase 1.
 * The seeder writes this catalog. Tests lock it. Product changes need Michael's approval.
 */
final class RoleCatalog
{
    /**
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        return [
            'platform.organizations.view' => 'View organizations',
            'platform.organizations.manage' => 'Manage organizations',
            'platform.fees.view' => 'View platform fee rules',
            'platform.fees.manage' => 'Manage platform fee rules',
            'platform.audit.view' => 'View the platform audit log',
            'platform.settings.manage' => 'Manage platform settings',
            'organization.view' => 'View the organization',
            'organization.settings.update' => 'Update organization settings',
            'organization.members.view' => 'View members',
            'organization.members.manage' => 'Manage members',
            'events.view' => 'View events',
            'events.manage' => 'Manage events',
            'tickets.sell' => 'Sell tickets at the box office',
            'checkin.scan' => 'Check attendees in',
            'finance.view' => 'View finance',
            'finance.refunds.decide' => 'Decide refunds',
            'orders.view_own' => 'View own orders',
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionSlugs(): array
    {
        return array_keys(self::permissions());
    }

    /**
     * @return array<string, array{name: string, scope: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        $platform = [
            'platform.organizations.view',
            'platform.organizations.manage',
            'platform.fees.view',
            'platform.fees.manage',
            'platform.audit.view',
            'platform.settings.manage',
        ];

        $owner = [
            'organization.view',
            'organization.settings.update',
            'organization.members.view',
            'organization.members.manage',
            'events.view',
            'events.manage',
            'tickets.sell',
            'checkin.scan',
            'finance.view',
            'finance.refunds.decide',
        ];

        return [
            'platform_super_admin' => [
                'name' => 'Platform super admin',
                'scope' => 'platform',
                'permissions' => $platform,
            ],
            'platform_support_admin' => [
                'name' => 'Platform support admin',
                'scope' => 'platform',
                'permissions' => [
                    'platform.organizations.view',
                    'platform.fees.view',
                    'platform.audit.view',
                ],
            ],
            'org_owner' => [
                'name' => 'Organization owner',
                'scope' => 'organization',
                'permissions' => $owner,
            ],
            'org_admin' => [
                'name' => 'Organization admin',
                'scope' => 'organization',
                'permissions' => array_values(array_diff($owner, ['finance.refunds.decide'])),
            ],
            'event_manager' => [
                'name' => 'Event manager',
                'scope' => 'organization',
                'permissions' => [
                    'organization.view',
                    'events.view',
                    'events.manage',
                    'checkin.scan',
                ],
            ],
            'box_office' => [
                'name' => 'Box office',
                'scope' => 'organization',
                'permissions' => [
                    'organization.view',
                    'events.view',
                    'tickets.sell',
                    'checkin.scan',
                ],
            ],
            'check_in_staff' => [
                'name' => 'Check-in staff',
                'scope' => 'organization',
                'permissions' => [
                    'organization.view',
                    'events.view',
                    'checkin.scan',
                ],
            ],
            'finance' => [
                'name' => 'Finance',
                'scope' => 'organization',
                'permissions' => [
                    'organization.view',
                    'events.view',
                    'finance.view',
                    'finance.refunds.decide',
                ],
            ],
            'buyer' => [
                'name' => 'Buyer',
                'scope' => 'organization',
                'permissions' => [
                    'orders.view_own',
                ],
            ],
        ];
    }

    public static function isOrganizationRole(string $slug): bool
    {
        $role = self::roles()[$slug] ?? null;

        return is_array($role) && $role['scope'] === 'organization';
    }

    public static function assertConsistent(): void
    {
        $known = self::permissionSlugs();

        foreach (self::roles() as $slug => $role) {
            if (! in_array($role['scope'], ['platform', 'organization'], true)) {
                throw new InvalidArgumentException('Unknown scope for role '.$slug.'.');
            }

            foreach ($role['permissions'] as $permission) {
                if (! in_array($permission, $known, true)) {
                    throw new InvalidArgumentException('Role '.$slug.' grants unknown permission '.$permission.'.');
                }
            }
        }
    }
}
