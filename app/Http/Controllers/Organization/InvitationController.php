<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Invitations\InvitationAcceptor;
use App\Services\Invitations\InvitationClosed;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InvitationController extends Controller
{
    public function __construct(private readonly InvitationAcceptor $acceptor) {}

    public function show(string $token): Response
    {
        $invitation = OrganizationInvitation::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('token_hash', hash('sha256', $token))
            ->with('role')
            ->first();

        $organizationId = app(OrganizationManager::class)->id();

        if ($invitation instanceof OrganizationInvitation && $invitation->organization_id !== $organizationId) {
            abort(403);
        }

        return response()->view('organization.invitation', [
            'token' => $token,
            'invitation' => $invitation,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        try {
            $this->acceptor->accept($user, $token);
        } catch (InvitationClosed $exception) {
            return response()->view('organization.invitation', [
                'token' => $token,
                'invitation' => null,
                'message' => $exception->getMessage(),
            ], $exception->status);
        }

        return redirect()->route('dashboard')->with('status', 'Invitation accepted.');
    }
}
