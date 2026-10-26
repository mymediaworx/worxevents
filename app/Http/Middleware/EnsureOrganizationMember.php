<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\Access;
use App\Tenancy\OrganizationManager;
use App\Tenancy\TenancyException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOrganizationMember
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly Access $access,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $this->organizations->id();

        if ($request->attributes->get('host_kind') !== 'organization' || $organizationId === null) {
            throw TenancyException::notFound();
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $membership = $this->access->membership($user, $organizationId);

        if ($membership === null) {
            throw TenancyException::forbidden();
        }

        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
