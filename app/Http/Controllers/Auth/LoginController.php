<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

final class LoginController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function create(): Response
    {
        return response()->view('auth.login');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], 'auth.login');

        $email = strtolower((string) $validated['email']);
        $password = (string) $validated['password'];
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            $this->audit->write('auth.login_failed', $this->organizations->id(), null, [
                'email' => $email,
            ]);

            return $this->invalid('Those credentials do not match our records.');
        }

        if ($request->attributes->get('host_kind') === 'platform') {
            if ($this->access->platformMembership($user) === null) {
                $this->audit->write('auth.login_denied', null, $user, ['reason' => 'platform']);
                abort(403);
            }
        } else {
            $organizationId = $this->organizations->id();

            if ($organizationId === null || $this->access->membership($user, $organizationId) === null) {
                $this->audit->write('auth.login_denied', $organizationId, $user, ['reason' => 'organization']);
                abort(403);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        $this->audit->write('auth.login', $this->organizations->id(), $user, []);

        $home = $request->attributes->get('host_kind') === 'platform'
            ? route('platform.dashboard')
            : route('dashboard');

        return redirect()->intended($home);
    }

    private function invalid(string $message): Response
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag(['email' => $message]));

        return response()->view('auth.login', ['errors' => $bag], 422);
    }
}
