<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\PageSize;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    public function __invoke(Request $request): Response
    {
        $perPage = PageSize::clamp((int) $request->query('per_page', 25));

        $organizations = $this->organizations->bypass(function () use ($perPage) {
            return Organization::query()->orderBy('name')->paginate($perPage);
        });

        $audits = $this->organizations->bypass(function () {
            return AuditLog::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->latest('created_at')
                ->limit(25)
                ->get();
        });

        return response()->view('platform.dashboard', [
            'organizations' => $organizations,
            'audits' => $audits,
        ]);
    }
}
