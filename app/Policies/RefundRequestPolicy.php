<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RefundRequest;
use App\Models\User;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;

final class RefundRequestPolicy
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly Access $access,
    ) {}

    public function view(User $user, RefundRequest $refund): bool
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null || $refund->organization_id !== $organizationId) {
            return false;
        }

        return $this->access->allows($user, $organizationId, 'finance.view');
    }

    public function decide(User $user, RefundRequest $refund): bool
    {
        $organizationId = $this->organizations->id();

        if ($organizationId === null || $refund->organization_id !== $organizationId) {
            return false;
        }

        return $this->access->allows($user, $organizationId, 'finance.refunds.decide');
    }
}
