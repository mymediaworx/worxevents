<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use App\Tenancy\TenancyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password;

final class RegisterController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    public function create(Request $request): Response
    {
        $this->requireOrganization($request);

        return response()->view('auth.register');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $organizationId = $this->requireOrganization($request);

        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ], 'auth.register');

        $email = strtolower((string) $validated['email']);
        $password = (string) $validated['password'];
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && ! Hash::check($password, $user->password)) {
            return response()->view('auth.register', [
                'errors' => $this->bag('email', 'Those credentials do not match our records.'),
            ], 422);
        }

        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => (string) $validated['name'],
                'email' => $email,
                'password' => $password,
            ]);
            $user->sendEmailVerificationNotification();
        }

        $role = Role::query()->where('slug', 'buyer')->firstOrFail();
        $membership = OrganizationMember::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership instanceof OrganizationMember) {
            OrganizationMember::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => MembershipStatus::Active,
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        $this->audit->write('auth.registered', $organizationId, $user, ['email' => $email]);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('dashboard');
    }

    private function requireOrganization(Request $request): string
    {
        if ($request->attributes->get('host_kind') !== 'organization') {
            throw TenancyException::notFound();
        }

        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            throw TenancyException::notFound();
        }

        return $organizationId;
    }

    private function bag(string $key, string $message): ViewErrorBag
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag([$key => $message]));

        return $bag;
    }
}
