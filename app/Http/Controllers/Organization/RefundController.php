<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Fees\MoneyFormat;
use App\Domain\Payments\GatewayDisabled;
use App\Domain\Payments\GatewayUnconfigured;
use App\Domain\Refunds\OrganizerRefundWorkflow;
use App\Domain\Refunds\RefundNotConfirmed;
use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class RefundController extends Controller
{
    public function __construct(private readonly OrganizerRefundWorkflow $workflow) {}

    public function index(): Response
    {
        return response()->view('organization.finance', [
            'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $this->validateOr422($request, [
            'order_key' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'string', 'max:20'],
            'note' => ['required', 'string', 'max:500'],
        ], 'organization.finance', [
            'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
        ]);

        try {
            $cents = MoneyFormat::dollarsToCents((string) $validated['amount']);
            $this->workflow->request(
                $user,
                (string) $validated['order_key'],
                $cents,
                (string) $validated['note'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->view('organization.finance', [
                'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->view('organization.finance', [
            'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
            'status' => 'Refund requested. It is not complete until the payment provider confirms it.',
        ]);
    }

    public function submit(RefundRequest $refund): Response
    {
        try {
            $this->workflow->submit($refund);
        } catch (GatewayUnconfigured|GatewayDisabled $exception) {
            return response()->view('organization.finance', [
                'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
                'message' => $exception->getMessage(),
            ], 409);
        } catch (RefundNotConfirmed $exception) {
            return response()->view('organization.finance', [
                'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->view('organization.finance', [
            'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
            'status' => 'Submitted to the payment provider. Waiting for confirmation.',
        ]);
    }
}
