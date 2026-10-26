<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Fees\MoneyFormat;
use App\Domain\Payments\AuthorizeNetGateway;
use App\Domain\Payments\GatewayDisabled;
use App\Domain\Payments\GatewayUnconfigured;
use App\Domain\Payments\PaymentGatewayManager;
use App\Domain\Refunds\OrganizerRefundWorkflow;
use App\Domain\Refunds\RefundCompletion;
use App\Domain\Refunds\RefundNotConfirmed;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class RefundController extends Controller
{
    public function __construct(
        private readonly OrganizerRefundWorkflow $workflow,
        private readonly RefundCompletion $completion,
        private readonly PaymentGatewayManager $gateways,
        private readonly OrganizationManager $organizations,
    ) {}

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
            $orderKey = $this->orderKey((string) $validated['order_key'], $cents);
            $this->workflow->request(
                $user,
                $orderKey,
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

    public function confirm(RefundRequest $refund): Response
    {
        if ($refund->status !== RefundStatus::Submitted || $refund->provider_reference === null) {
            return response()->view('organization.finance', [
                'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
                'message' => 'Only a submitted refund can be confirmed.',
            ], 422);
        }

        $gateway = $this->gateways->gateway($refund->provider);

        if (! $gateway instanceof AuthorizeNetGateway) {
            return response()->view('organization.finance', [
                'refunds' => RefundRequest::query()->latest()->limit(50)->get(),
                'message' => 'This provider cannot confirm a refund from here.',
            ], 409);
        }

        $organizationId = $this->organizations->id();

        if ($organizationId === null) {
            abort(403);
        }

        $approved = false;

        try {
            $result = $gateway->lookup($organizationId, $refund->provider_reference);
            $approved = $result->accepted && $result->status === 'approved' && $result->reference === $refund->provider_reference;
            $confirmed = $this->workflow->confirm($refund, $refund->provider_reference, $approved);

            if ($approved) {
                $this->completion->apply($confirmed);
            }
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
            'status' => $approved ? 'The provider confirmed this refund.' : 'The provider did not confirm this refund.',
        ]);
    }

    private function orderKey(string $key, int $cents): string
    {
        $order = Order::query()->where('reference', $key)->first();

        if (! $order instanceof Order) {
            $order = Order::query()->whereKey($key)->first();
        }

        if (! $order instanceof Order) {
            return $key;
        }

        $reserved = 0;
        $rows = RefundRequest::query()
            ->where('order_key', $order->id)
            ->whereIn('status', [
                RefundStatus::Requested->value,
                RefundStatus::Submitted->value,
                RefundStatus::Completed->value,
            ])
            ->get(['amount_cents']);

        foreach ($rows as $row) {
            $reserved += $row->amount_cents;
        }

        if ($reserved + $cents > $order->total_cents) {
            throw new InvalidArgumentException('That refund is larger than the remaining charge.');
        }

        return $order->id;
    }
}
