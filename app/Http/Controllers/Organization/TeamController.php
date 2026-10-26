<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Authorization\RoleCatalog;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Notifications\OrganizationInvitationNotification;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class TeamController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function index(Request $request): Response
    {
        return response()->view('organization.team', [
            'roles' => Role::query()->where('scope', 'organization')->where('slug', '!=', 'org_owner')->orderBy('name')->get(),
            'invitations' => OrganizationInvitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->orderByDesc('created_at')->limit(25)->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
        ], 'organization.team', [
            'roles' => Role::query()->where('scope', 'organization')->where('slug', '!=', 'org_owner')->orderBy('name')->get(),
            'invitations' => OrganizationInvitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->orderByDesc('created_at')->limit(25)->get(),
        ]);

        $slug = (string) $validated['role'];

        if (! RoleCatalog::isOrganizationRole($slug) || $slug === 'org_owner') {
            abort(422, 'That role cannot be invited.');
        }

        $role = Role::query()->where('slug', $slug)->where('scope', 'organization')->first();

        if ($role === null) {
            abort(422, 'That role cannot be invited.');
        }

        $organization = $this->organizations->get();
        $organizationId = $this->organizations->id();

        if ($organization === null || $organizationId === null) {
            abort(404);
        }

        $email = strtolower((string) $validated['email']);
        $token = Str::random(64);
        $hostname = $request->getHost();

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organizationId,
            'email' => $email,
            'role_id' => $role->id,
            'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $request->user()?->getAuthIdentifier(),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(new OrganizationInvitationNotification(
            $token,
            $hostname,
            $organization->name,
        ));

        $this->audit->write('organization.invitation_created', $organizationId, $invitation, [
            'email' => $email,
            'role' => $slug,
        ]);

        return redirect()->route('team')->with('status', 'Invitation sent.');
    }
}
