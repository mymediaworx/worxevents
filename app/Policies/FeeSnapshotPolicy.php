<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FeeSnapshot;
use App\Models\User;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;

final class FeeSnapshotPolicy
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly Access $access,
    ) {}

    public function view(User $user, FeeSnapshot $snapshot): bool
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null || $snapshot->organization_id !== $organizationId) {
            return false;
        }

        return $this->access->allows($user, $organizationId, 'finance.view')
            || $this->access->allows($user, $organizationId, 'organization.view');
    }
}
