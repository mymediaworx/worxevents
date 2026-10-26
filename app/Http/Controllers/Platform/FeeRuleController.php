<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Fees\MoneyFormat;
use App\Http\Controllers\Controller;
use App\Models\FeeRule;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class FeeRuleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return response()->view('platform.fees', [
            'rules' => FeeRule::query()->orderByDesc('effective_at')->get(),
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'percent' => ['required', 'string', 'max:8'],
            'minimum' => ['required', 'string', 'max:12'],
            'maximum' => ['required', 'string', 'max:12'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ], 'platform.fees', [
            'rules' => FeeRule::query()->orderByDesc('effective_at')->get(),
        ]);

        try {
            $percent = MoneyFormat::percentToBps((string) $validated['percent']);
            $minimum = MoneyFormat::dollarsToCents((string) $validated['minimum']);
            $maximum = MoneyFormat::dollarsToCents((string) $validated['maximum']);
        } catch (InvalidArgumentException $exception) {
            return response()->view('platform.fees', [
                'rules' => FeeRule::query()->orderByDesc('effective_at')->get(),
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($minimum > $maximum || $maximum > 1_000_000) {
            return response()->view('platform.fees', [
                'rules' => FeeRule::query()->orderByDesc('effective_at')->get(),
                'message' => 'The minimum must be less than or equal to the maximum, and the maximum cannot exceed 10000.00.',
            ], 422);
        }

        $rule = FeeRule::query()->create([
            'organization_id' => null,
            'percent_bps' => $percent,
            'minimum_cents' => $minimum,
            'maximum_cents' => $maximum,
            'currency' => 'USD',
            'effective_at' => $validated['effective_on'].' 00:00:00',
            'created_by_user_id' => $request->user()?->getAuthIdentifier(),
            'note' => $validated['note'] ?? null,
        ]);

        $this->audit->write('platform.fee_rule_created', null, $rule, [
            'percent_bps' => $percent,
            'minimum_cents' => $minimum,
            'maximum_cents' => $maximum,
            'effective_at' => (string) $validated['effective_on'],
        ]);

        return redirect()->route('platform.fees')->with('status', 'Fee rule saved. It applies to new orders only.');
    }
}
