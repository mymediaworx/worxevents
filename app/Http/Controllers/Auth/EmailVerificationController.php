<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasVerifiedEmail()) {
            return redirect()->route($request->attributes->get('host_kind') === 'platform' ? 'platform.dashboard' : 'dashboard');
        }

        return response()->view('auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || strlen($user->id) !== strlen($id) || ! hash_equals($user->id, $id)) {
            abort(403);
        }

        $expected = sha1($user->email);

        if (strlen($expected) !== strlen($hash) || ! hash_equals($expected, $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->audit->write('auth.email_verified', $this->organizations->id(), $user, []);
        }

        return redirect()
            ->route($request->attributes->get('host_kind') === 'platform' ? 'platform.dashboard' : 'dashboard')
            ->with('status', 'Email verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent.');
    }
}
