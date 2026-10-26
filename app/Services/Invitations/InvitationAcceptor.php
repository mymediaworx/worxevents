<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Enums\MembershipStatus;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;

final class InvitationAcceptor
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly AuditLogger $audit,
    ) {}

    public function accept(User $user, string $token): OrganizationInvitation
    {
        $organizationId = $this->organizations->id();
        $invitation = OrganizationInvitation::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation instanceof OrganizationInvitation) {
            throw new InvitationClosed('This invitation is not valid.', 422);
        }

        if ($organizationId === null || $invitation->organization_id !== $organizationId) {
            throw new InvitationClosed('This invitation belongs to a different organization.', 403);
        }

        if ($invitation->revoked_at !== null || $invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
            throw new InvitationClosed('This invitation has expired or was already used.', 422);
        }

        if (strcasecmp($user->email, $invitation->email) !== 0) {
            throw new InvitationClosed('Sign in with the invited email address.', 403);
        }

        DB::transaction(function () use ($invitation, $user, $organizationId): void {
            $existing = OrganizationMember::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->id)
                ->first();

            if ($existing instanceof OrganizationMember) {
                $existing->role_id = $invitation->role_id;
                $existing->status = MembershipStatus::Active;
                $existing->save();
            } else {
                OrganizationMember::query()->create([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'role_id' => $invitation->role_id,
                    'status' => MembershipStatus::Active,
                ]);
            }

            $invitation->accepted_at = now();
            $invitation->save();
        });

        $this->audit->write(
            action: 'organization.invitation_accepted',
            organizationId: $organizationId,
            subject: $invitation,
            properties: ['email' => $invitation->email],
        );

        return $invitation->refresh();
    }
}
