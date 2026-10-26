<?php

declare(strict_types=1);

namespace Tests\Feature\Refunds;

use App\Domain\Payments\StripeConnectGateway;
use App\Domain\Refunds\OrganizerRefundWorkflow;
use App\Domain\Refunds\RefundNotConfirmed;
use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizerRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_does_not_complete_a_refund_and_confirmation_checks_the_provider_reference(): void
    {
        $workspace = $this->workspace('alpha.test', 'finance');
        app(OrganizationManager::class)->set($workspace['organization']);
        $workflow = app(OrganizerRefundWorkflow::class);

        $refund = $workflow->request($workspace['user'], 'order-9', 2500, 'Guest left.');
        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertNull($refund->completed_at);

        $submitted = $workflow->submit($refund);
        $this->assertSame(RefundStatus::Submitted, $submitted->status);
        $this->assertNull($submitted->completed_at);
        $this->assertNotNull($submitted->provider_reference);

        try {
            $workflow->confirm($submitted, 'wrong-reference', true);
            $this->fail('A mismatched provider reference was accepted.');
        } catch (RefundNotConfirmed) {
            $this->assertSame(RefundStatus::Submitted, $submitted->fresh()->status);
        }

        $completed = $workflow->confirm($submitted->fresh(), (string) $submitted->provider_reference, true);

        $this->assertSame(RefundStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_stripe_submit_does_not_call_stripe_or_complete_the_refund_when_unconfigured(): void
    {
        config([
            'payments.default' => 'stripe',
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
        ]);

        $workspace = $this->workspace('alpha.test', 'org_owner');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/finance/refunds'), [
            'order_key' => 'order-stripe',
            'amount' => '12.00',
            'note' => 'Organizer approved.',
        ])->assertOk();

        $refund = RefundRequest::query()->withoutGlobalScope(OrganizationScope::class)->firstOrFail();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/finance/refunds/'.$refund->id.'/submit'))
            ->assertStatus(409);

        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        $this->assertFalse(app(StripeConnectGateway::class)->isReady());
    }
}
