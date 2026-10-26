<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WorkspacePasswordReset;
use App\Services\AuditLogger;
use App\Support\PasswordResetTokens;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function requestForm(): Response
    {
        return response()->view('auth.forgot-password');
    }

    public function send(Request $request): Response
    {
        $validated = $this->validateOr422($request, [
            'email' => ['required', 'email', 'max:255'],
        ], 'auth.forgot-password');

        $email = strtolower((string) $validated['email']);
        $user = User::query()->where('email', $email)->first();
        $allowed = false;

        if ($user instanceof User) {
            if ($request->attributes->get('host_kind') === 'platform') {
                $allowed = $this->access->platformMembership($user) !== null;
            } else {
                $organizationId = $this->organizations->id();
                $allowed = $organizationId !== null && $this->access->membership($user, $organizationId) !== null;
            }
        }

        if ($user instanceof User && $allowed) {
            $token = PasswordResetTokens::create($user);
            $user->notify(new WorkspacePasswordReset($token, $request->getHost()));
            $this->audit->write('auth.password_reset_requested', $this->organizations->id(), $user, []);
        }

        return response()->view('auth.forgot-password', [
            'status' => 'If an account with that email can sign in here, we sent a reset link.',
        ]);
    }

    public function resetForm(Request $request, string $token): Response
    {
        return response()->view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): Response|RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ], 'auth.reset-password', [
            'token' => (string) $request->input('token'),
            'email' => (string) $request->input('email'),
        ]);

        $email = strtolower((string) $validated['email']);
        $status = Password::reset(
            [
                'email' => $email,
                'password' => (string) $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ]);

                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }

                $user->save();
                $this->audit->write('auth.password_reset', $this->organizations->id(), $user, []);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $bag = new ViewErrorBag;
            $bag->put('default', new MessageBag(['email' => 'This reset link is invalid or has expired.']));

            return response()->view('auth.reset-password', [
                'errors' => $bag,
                'token' => (string) $validated['token'],
                'email' => $email,
            ], 422);
        }

        return redirect()->route('login')->with('status', 'Password updated. Sign in with the new password.');
    }
}
