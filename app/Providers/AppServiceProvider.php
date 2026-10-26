<?php

declare(strict_types=1);

namespace App\Providers;

use App\Authorization\RoleCatalog;
use App\Domain\Payments\AuthorizeNetClient;
use App\Domain\Payments\FakeAuthorizeNetClient;
use App\Domain\Payments\HttpAuthorizeNetClient;
use App\Models\FeeSnapshot;
use App\Models\RefundRequest;
use App\Models\User;
use App\Policies\FeeSnapshotPolicy;
use App\Policies\RefundRequestPolicy;
use App\Support\CookieSecurity;
use App\Support\Navigation;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrganizationManager::class);
        $this->app->singleton(AuthorizeNetClient::class, function (Application $app): AuthorizeNetClient {
            if ($app->environment('testing')) {
                return new FakeAuthorizeNetClient;
            }

            return new HttpAuthorizeNetClient;
        });
    }

    public function boot(): void
    {
        config([
            'session.secure' => CookieSecurity::secure($this->app->environment(), config('session.secure')),
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('password-email', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('checkout', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) $request->ip());
        });

        Gate::before(function (User $user, string $ability): ?bool {
            if (! in_array($ability, RoleCatalog::permissionSlugs(), true)) {
                return null;
            }

            $organizationId = app(OrganizationManager::class)->id();

            if ($organizationId === null) {
                return app(Access::class)->platformAllows($user, $ability);
            }

            return app(Access::class)->allows($user, $organizationId, $ability);
        });

        Gate::policy(FeeSnapshot::class, FeeSnapshotPolicy::class);
        Gate::policy(RefundRequest::class, RefundRequestPolicy::class);

        View::composer(['layouts.app', 'layouts.platform'], function ($view): void {
            $user = auth()->user();
            $view->with('navLinks', Navigation::visible($user instanceof User ? $user : null));
            $view->with('workspaces', Navigation::workspaces($user instanceof User ? $user : null));
        });

        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
