<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\Access;
use App\Tenancy\TenancyException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformMember
{
    public function __construct(private readonly Access $access) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('host_kind') !== 'platform') {
            throw TenancyException::notFound();
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $membership = $this->access->platformMembership($user);

        if ($membership === null) {
            throw TenancyException::forbidden();
        }

        $request->attributes->set('platform_membership', $membership);

        return $next($request);
    }
}
