<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OrganizationDomain;
use App\Support\Hostname;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use App\Tenancy\TenancyException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveHost
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = Hostname::normalize($request->getHost());

        if (Hostname::isPlatform($host)) {
            $this->rejectClientOrganization($request, null);
            $request->attributes->set('host_kind', 'platform');
            $request->attributes->set('organization', null);
            view()->share('currentOrganization', null);
            view()->share('hostKind', 'platform');

            return $next($request);
        }

        $domain = OrganizationDomain::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('hostname', $host)
            ->whereNotNull('verified_at')
            ->first();

        if (! $domain instanceof OrganizationDomain || $domain->organization === null || ! $domain->organization->isAvailable()) {
            if ($domain instanceof OrganizationDomain && $domain->organization !== null && ! $domain->organization->isAvailable()) {
                throw TenancyException::unavailable();
            }

            throw TenancyException::notFound();
        }

        $organization = $domain->organization;
        $this->rejectClientOrganization($request, $organization->id);
        $this->organizations->set($organization);
        $request->attributes->set('host_kind', 'organization');
        $request->attributes->set('organization', $organization);
        view()->share('currentOrganization', $organization);
        view()->share('hostKind', 'organization');

        try {
            return $next($request);
        } finally {
            $this->organizations->forget();
        }
    }

    private function rejectClientOrganization(Request $request, ?string $organizationId): void
    {
        $supplied = [];

        if ($request->headers->has('X-Organization-Id')) {
            $supplied[] = $request->headers->get('X-Organization-Id');
        }

        if ($request->exists('organization_id')) {
            $supplied[] = $request->input('organization_id');
        }

        foreach ($supplied as $value) {
            if (! is_string($value) || $value === '' || $value !== $organizationId) {
                throw TenancyException::mismatch();
            }
        }
    }
}
