<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\OrganizationScope;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_sign_in_and_the_session_is_regenerated(): void
    {
        $workspace = $this->workspace('alpha.test');

        $this->startSession();
        $before = session()->getId();

        $response = $this->post($this->hostUrl('alpha.test', '/login'), [
            'email' => $workspace['user']->email,
            'password' => 'password',
        ]);

        $response->assertRedirect($this->hostUrl('alpha.test', '/dashboard'));
        $this->assertAuthenticatedAs($workspace['user']);
        $this->assertNotSame($before, session()->getId());
        $this->assertTrue(
            AuditLog::query()->withoutGlobalScope(OrganizationScope::class)->where('action', 'auth.login')->exists(),
        );
    }

    public function test_bad_credentials_are_422_and_the_password_is_not_audited(): void
    {
        $workspace = $this->workspace('alpha.test');

        $this->post($this->hostUrl('alpha.test', '/login'), [
            'email' => $workspace['user']->email,
            'password' => 'not-the-password',
        ])->assertStatus(422);

        $log = AuditLog::query()->withoutGlobalScope(OrganizationScope::class)->where('action', 'auth.login_failed')->first();
        $this->assertNotNull($log);
        $encoded = (string) json_encode($log->properties);
        $this->assertStringNotContainsString('not-the-password', $encoded);
        $this->assertGuest();
    }

    public function test_a_member_of_another_organization_is_forbidden(): void
    {
        $alpha = $this->workspace('alpha.test');
        $this->workspace('beta.test');

        $this->post($this->hostUrl('beta.test', '/login'), [
            'email' => $alpha['user']->email,
            'password' => 'password',
        ])->assertForbidden();

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $workspace = $this->workspace('alpha.test');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($this->hostUrl('alpha.test', '/login'), [
                'email' => $workspace['user']->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->post($this->hostUrl('alpha.test', '/login'), [
            'email' => $workspace['user']->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_password_reset_expires_and_a_cross_organization_request_sends_nothing(): void
    {
        $alpha = $this->workspace('alpha.test');
        $this->workspace('beta.test');

        $this->post($this->hostUrl('beta.test', '/forgot-password'), [
            'email' => $alpha['user']->email,
        ])->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->post($this->hostUrl('alpha.test', '/forgot-password'), [
            'email' => $alpha['user']->email,
        ])->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 1);

        $token = Password::broker()->createToken($alpha['user']);
        $this->travel(61)->minutes();

        $this->post($this->hostUrl('alpha.test', '/reset-password'), [
            'token' => $token,
            'email' => $alpha['user']->email,
            'password' => 'Correct-horse-1',
            'password_confirmation' => 'Correct-horse-1',
        ])->assertStatus(422);

        $this->travelBack();
        $fresh = Password::broker()->createToken($alpha['user']->fresh());

        $this->post($this->hostUrl('alpha.test', '/reset-password'), [
            'token' => $fresh,
            'email' => $alpha['user']->email,
            'password' => 'Correct-horse-1',
            'password_confirmation' => 'Correct-horse-1',
        ])->assertRedirect($this->hostUrl('alpha.test', '/login'));

        $this->assertTrue(Hash::check('Correct-horse-1', (string) $alpha['user']->fresh()->password));
    }

    public function test_unverified_buyers_cannot_open_the_dashboard_until_they_verify(): void
    {
        $workspace = $this->workspace('alpha.test', 'buyer', User::factory()->unverified()->create());

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/dashboard'))
            ->assertRedirect($this->hostUrl('alpha.test', '/email/verify'));

        URL::forceRootUrl('http://alpha.test');
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $workspace['user']->id,
            'hash' => sha1($workspace['user']->email),
        ]);

        $this->actingAs($workspace['user'])->get($url)->assertRedirect($this->hostUrl('alpha.test', '/dashboard'));
        $this->assertNotNull($workspace['user']->fresh()->email_verified_at);
    }

    public function test_registration_creates_a_buyer_on_this_organization_only(): void
    {
        $workspace = $this->workspace('alpha.test');

        $this->post($this->hostUrl('alpha.test', '/register'), [
            'name' => 'Pat Buyer',
            'email' => 'pat@example.com',
            'password' => 'Correct-horse-1',
            'password_confirmation' => 'Correct-horse-1',
        ])->assertRedirect($this->hostUrl('alpha.test', '/email/verify'));

        $user = User::query()->where('email', 'pat@example.com')->firstOrFail();
        $membership = $user->memberships()->withoutGlobalScope(OrganizationScope::class)->with('role')->firstOrFail();
        $this->assertSame('buyer', $membership->role->slug);
        $this->assertSame($workspace['organization']->id, $membership->organization_id);
    }

    public function test_csrf_middleware_rejects_a_mismatched_token(): void
    {
        $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $request = Request::create('/login', 'POST', [
            'email' => 'a@example.com',
            'password' => 'secret',
        ]);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, fn () => response('ok'));
    }

    public function test_session_cookies_are_http_only_and_lax(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse((bool) config('session.secure'));
    }
}
