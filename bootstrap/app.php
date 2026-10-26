<?php

use App\Http\Middleware\EnsureOrganizationMember;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformMember;
use App\Http\Middleware\ResolveHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Host resolution has to run before route-model binding, or tenant
        // scopes hide the bound model and the request 404s.
        $middleware->web(prepend: [
            ResolveHost::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/authorize-net/*',
        ]);
        $middleware->alias([
            'organization.member' => EnsureOrganizationMember::class,
            'platform.member' => EnsurePlatformMember::class,
            'permission' => EnsurePermission::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (Request $request): string {
            return $request->attributes->get('host_kind') === 'platform'
                ? route('platform.dashboard')
                : route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
