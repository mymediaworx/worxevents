<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Organization;
use App\Models\PaymentSetting;
use App\Models\PlatformPlan;
use App\Models\SecurityEvent;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminController extends Controller
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly AuditLogger $audit,
    ) {}

    public function users(): Response
    {
        $users = $this->organizations->bypass(function () {
            return User::query()
                ->with(['memberships.role', 'memberships.organization', 'platformMembership.role'])
                ->orderBy('email')
                ->limit(100)
                ->get();
        });

        return response()->view('platform.users', [
            'title' => 'Users',
            'users' => $users,
        ]);
    }

    public function status(Request $request, Organization $organization): Response
    {
        $validated = $this->validateOr422($request, [
            'status' => ['required', 'in:active,suspended'],
        ], 'platform.dashboard', $this->dashboardData());

        $organization->forceFill([
            'status' => OrganizationStatus::from((string) $validated['status']),
        ])->save();
        $this->audit->write('platform.organization_status', $organization->id, $organization, [
            'status' => $organization->status->value,
        ]);

        return response()->view('platform.dashboard', array_merge($this->dashboardData(), [
            'status' => $organization->name.' is '.$organization->status->value.'.',
        ]));
    }

    public function plans(): Response
    {
        return response()->view('platform.plans', [
            'title' => 'Plans',
            'plans' => PlatformPlan::query()->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function storePlan(Request $request): Response
    {
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount_cents' => ['required', 'integer', 'min:0'],
        ], 'platform.plans', [
            'title' => 'Plans',
            'plans' => PlatformPlan::query()->orderBy('name')->limit(100)->get(),
        ]);

        PlatformPlan::query()->create([
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount_cents' => (int) $validated['amount_cents'],
            'active' => $request->boolean('active'),
        ]);

        return response()->view('platform.plans', [
            'title' => 'Plans',
            'plans' => PlatformPlan::query()->orderBy('name')->limit(100)->get(),
            'status' => 'Plan saved. It does not charge a card.',
        ]);
    }

    public function payments(): Response
    {
        $rows = $this->organizations->bypass(function () {
            return PaymentSetting::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->with('organization')
                ->orderBy('validated_at')
                ->limit(100)
                ->get();
        });

        return response()->view('platform.payments', [
            'title' => 'Payment oversight',
            'settings' => $rows,
        ]);
    }

    public function disputes(): Response
    {
        return response()->view('platform.disputes', $this->disputePage());
    }

    public function storeDispute(Request $request): Response
    {
        $validated = $this->validateOr422($request, [
            'for_organization' => ['nullable', 'string'],
            'summary' => ['required', 'string', 'max:500'],
        ], 'platform.disputes', $this->disputePage());

        $organizationId = $validated['for_organization'] ?? null;
        $organizationId = is_string($organizationId) && $organizationId !== '' ? $organizationId : null;

        if ($organizationId !== null && ! Organization::query()->whereKey($organizationId)->exists()) {
            return response()->view('platform.disputes', array_merge($this->disputePage(), [
                'message' => 'That organization was not found.',
            ]), 404);
        }

        Dispute::query()->create([
            'organization_id' => $organizationId,
            'status' => 'open',
            'summary' => (string) $validated['summary'],
        ]);

        return response()->view('platform.disputes', array_merge($this->disputePage(), [
            'status' => 'Dispute opened.',
        ]));
    }

    public function disputeStatus(Request $request, Dispute $dispute): Response
    {
        $validated = $this->validateOr422($request, [
            'status' => ['required', 'in:open,won,lost'],
        ], 'platform.disputes', $this->disputePage());

        $dispute->forceFill(['status' => (string) $validated['status']])->save();

        return response()->view('platform.disputes', array_merge($this->disputePage(), [
            'status' => 'Dispute marked '.$dispute->status.'.',
        ]));
    }

    public function audit(): Response
    {
        $rows = $this->organizations->bypass(function () {
            return AuditLog::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->latest('created_at')
                ->limit(100)
                ->get();
        });

        return response()->view('platform.audit', [
            'title' => 'Audit log',
            'audits' => $rows,
        ]);
    }

    public function jobs(): Response
    {
        $failed = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['uuid', 'queue', 'failed_at']);

        return response()->view('platform.jobs', [
            'title' => 'Jobs',
            'queued' => DB::table('background_jobs')->count(),
            'failed' => $failed,
        ]);
    }

    public function security(): Response
    {
        return response()->view('platform.security', [
            'title' => 'Security events',
            'events' => SecurityEvent::query()->latest('created_at')->limit(100)->get(),
        ]);
    }

    public function health(): Response
    {
        $database = 'down';

        try {
            DB::select('select 1 as ok');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'down';
        }

        $status = $database === 'ok' ? 200 : 503;

        return response()->view('platform.health', [
            'title' => 'Health',
            'database' => $database,
            'failedJobs' => DB::table('failed_jobs')->count(),
        ], $status);
    }

    public function support(): Response
    {
        $cases = $this->organizations->bypass(function () {
            return SupportCase::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->with('organization')
                ->latest()
                ->limit(100)
                ->get();
        });

        return response()->view('platform.support', [
            'title' => 'Support cases',
            'cases' => $cases,
        ]);
    }

    public function closeSupport(string $supportCase): Response
    {
        $case = $this->organizations->bypass(function () use ($supportCase): ?SupportCase {
            $found = SupportCase::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->whereKey($supportCase)
                ->first();

            return $found instanceof SupportCase ? $found : null;
        });

        if (! $case instanceof SupportCase) {
            abort(404);
        }

        $this->organizations->bypass(function () use ($case): void {
            $case->forceFill(['status' => 'closed'])->save();
        });

        return response()->view('platform.support', [
            'title' => 'Support cases',
            'cases' => $this->organizations->bypass(function () {
                return SupportCase::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->with('organization')
                    ->latest()
                    ->limit(100)
                    ->get();
            }),
            'status' => 'Case closed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(): array
    {
        return [
            'organizations' => Organization::query()->orderBy('name')->limit(100)->get(),
            'audits' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disputePage(): array
    {
        return [
            'title' => 'Disputes',
            'disputes' => Dispute::query()->with('organization')->latest()->limit(100)->get(),
            'organizations' => Organization::query()->orderBy('name')->limit(100)->get(),
        ];
    }
}
