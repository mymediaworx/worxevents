<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Domain\Payments\AuthorizeNetGateway;
use App\Enums\EventStatus;
use App\Enums\FeeLedgerKind;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PaymentSetting;
use App\Models\TicketType;
use App\Models\User;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuthorizeNetCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_checkout_charges_the_server_total_and_issues_tickets_only_after_approval(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->saveSandbox($workspace['user'], 'alpha.test');
        $built = $this->paidEvent($workspace['organization']->id);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/e/jazz-night'))
            ->assertOk()
            ->assertSee('https://jstest.authorize.net/v1/Accept.js', false)
            ->assertSee('public-client-key')
            ->assertSee('Booking fee')
            ->assertDontSee('super-secret-key')
            ->assertDontSee('signature-key')
            ->assertDontSee('https://js.authorize.net/v1/Accept.js', false);

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/quote'), [
            'lines' => [
                $built['ticket']->id => ['quantity' => 1],
            ],
        ])->assertOk()->assertSee('Booking fee')->assertSee('1.50')->assertSee('51.50');

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'data_descriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
            'data_value' => 'opaque-token-value',
            'lines' => [
                $built['ticket']->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ])->assertOk()->assertSee('Booking fee')->assertSee('1.50')->assertSee('51.50')->assertDontSee('opaque-token-value');

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame(5000, (int) $order->ticket_subtotal_cents);
        $this->assertSame(150, (int) $order->fee_cents);
        $this->assertSame(5150, (int) $order->total_cents);
        $this->assertSame(OrderStatus::Completed->value, $order->status);
        $this->assertSame(PaymentStatus::Approved->value, $order->payment_status);
        $this->assertSame('authorize_net', $order->provider);
        $this->assertSame('4242', $order->card_last_four);
        $this->assertSame(1, DB::table('tickets')->count());
        $this->assertDatabaseHas('fee_snapshots', [
            'order_key' => $order->id,
            'fee_cents' => 150,
        ]);
        $this->assertDatabaseHas('fee_ledger_entries', [
            'order_key' => $order->id,
            'kind' => FeeLedgerKind::Assessed->value,
            'amount_cents' => 150,
        ]);
        $this->assertDatabaseHas('fee_ledger_entries', [
            'order_key' => $order->id,
            'kind' => FeeLedgerKind::Owed->value,
            'amount_cents' => 150,
        ]);
        $this->assertDatabaseMissing('fee_ledger_entries', [
            'order_key' => $order->id,
            'kind' => FeeLedgerKind::Collected->value,
        ]);

        $storedKey = (string) DB::table('payment_settings')->value('transaction_key');
        $this->assertNotSame('super-secret-key', $storedKey);
        $this->assertStringNotContainsString('super-secret-key', $storedKey);
    }

    public function test_a_declined_charge_releases_inventory_and_issues_no_tickets(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->saveSandbox($workspace['user'], 'alpha.test');
        $built = $this->paidEvent($workspace['organization']->id, 1);

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), $this->checkoutBody($built['ticket']->id, 'decline-token'))
            ->assertStatus(422)
            ->assertSee('declined');

        $this->assertSame(0, DB::table('tickets')->count());
        $this->assertSame(OrderStatus::Canceled->value, DB::table('orders')->value('status'));
        $this->assertSame(PaymentStatus::Declined->value, DB::table('orders')->value('payment_status'));
        $this->assertSame(0, (int) DB::table('ticket_types')->value('quantity_held'));
        $this->assertSame(0, (int) DB::table('ticket_types')->value('quantity_sold'));
        $this->assertDatabaseMissing('fee_ledger_entries', ['kind' => FeeLedgerKind::Owed->value]);
    }

    public function test_a_lost_approval_is_settled_once_by_a_signed_webhook(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->saveSandbox($workspace['user'], 'alpha.test');
        $built = $this->paidEvent($workspace['organization']->id);

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), $this->checkoutBody($built['ticket']->id, 'timeout-after-approve'))
            ->assertStatus(503)
            ->assertSee('did not respond');

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Pending->value, $order->status);
        $this->assertSame(0, DB::table('tickets')->count());

        $body = json_encode([
            'notificationId' => 'notice-1',
            'eventType' => 'net.authorize.payment.authcapture.created',
            'payload' => ['id' => 'anet_'.$order->id, 'responseCode' => 1],
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha512='.hash_hmac('sha512', $body, 'signature-key');

        $this->call('POST', $this->hostUrl('alpha.test', '/webhooks/authorize-net/'.$workspace['organization']->id), [], [], [], [
            'HTTP_X_ANET_SIGNATURE' => 'sha512='.hash_hmac('sha512', $body, 'wrong-key'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(401);
        $this->assertSame(0, DB::table('tickets')->count());
        $this->assertSame(0, DB::table('gateway_notifications')->count());

        $this->call('POST', $this->hostUrl('alpha.test', '/webhooks/authorize-net/'.$workspace['organization']->id), [], [], [], [
            'HTTP_X_ANET_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
        $this->call('POST', $this->hostUrl('alpha.test', '/webhooks/authorize-net/'.$workspace['organization']->id), [], [], [], [
            'HTTP_X_ANET_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame(1, DB::table('tickets')->count());
        $this->assertSame(1, DB::table('gateway_notifications')->count());
        $this->assertSame(OrderStatus::Completed->value, DB::table('orders')->value('status'));
        $this->assertDatabaseHas('fee_ledger_entries', [
            'kind' => FeeLedgerKind::Owed->value,
            'amount_cents' => 150,
        ]);
    }

    public function test_card_numbers_are_rejected_and_live_mode_stays_off(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->saveSandbox($workspace['user'], 'alpha.test');
        $built = $this->paidEvent($workspace['organization']->id);

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/payments'), [
                'environment' => 'live',
                'api_login_id' => 'login',
                'transaction_key' => 'super-secret-key',
                'signature_key' => 'signature-key',
                'public_client_key' => 'public-client-key',
            ])->assertStatus(422)->assertSee('Live Authorize.net')->assertDontSee('super-secret-key');

        $body = $this->checkoutBody($built['ticket']->id, 'opaque-token-value');
        $body['card_number'] = '4111111111111111';
        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), $body)
            ->assertStatus(422)
            ->assertSee('Card data is never accepted');
        $this->assertSame(0, DB::table('orders')->count());

        app(OrganizationManager::class)->set($workspace['organization']);
        PaymentSetting::query()->update(['environment' => 'live']);
        $this->assertFalse(app(AuthorizeNetGateway::class)->isReady());

        unset($body['card_number']);
        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), $body)
            ->assertStatus(422)
            ->assertSee('card processor');
        $this->assertSame(0, DB::table('orders')->count());
    }

    public function test_refunds_partial_and_full_do_not_return_the_booking_fee_and_invoices_remit_it(): void
    {
        config(['payments.default' => 'authorize_net']);
        $workspace = $this->workspace('alpha.test');
        $this->saveSandbox($workspace['user'], 'alpha.test');
        $built = $this->paidEvent($workspace['organization']->id);

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), $this->checkoutBody($built['ticket']->id, 'opaque-token-value'))
            ->assertOk();

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/finance/refunds'), [
            'order_key' => $order->reference,
            'amount' => '10.00',
            'note' => 'Partial ticket refund.',
        ])->assertOk();

        $refund = DB::table('refund_requests')->first();
        $this->assertNotNull($refund);
        $this->assertSame($order->id, $refund->order_key);

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/finance/refunds/'.$refund->id.'/submit'))
            ->assertOk()
            ->assertSee('Waiting for confirmation');
        $this->assertNull(DB::table('refund_requests')->value('completed_at'));

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/finance/refunds/'.$refund->id.'/confirm'))
            ->assertOk()
            ->assertSee('provider confirmed');

        $this->assertNotNull(DB::table('refund_requests')->value('completed_at'));
        $this->assertSame(PaymentStatus::PartiallyRefunded->value, DB::table('orders')->value('payment_status'));
        $this->assertSame(IssuedTicketStatus::Issued->value, DB::table('tickets')->value('status'));
        $this->assertDatabaseHas('fee_ledger_entries', ['kind' => FeeLedgerKind::Owed->value, 'amount_cents' => 150]);
        $this->assertDatabaseMissing('fee_ledger_entries', ['kind' => FeeLedgerKind::Remitted->value]);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/finance/refunds'), [
            'order_key' => $order->reference,
            'amount' => '40.00',
            'note' => 'Remainder of the ticket price.',
        ])->assertOk();
        $second = DB::table('refund_requests')->where('amount_cents', 4000)->first();
        $this->assertNotNull($second);
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/finance/refunds/'.$second->id.'/submit'))->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/finance/refunds/'.$second->id.'/confirm'))->assertOk();

        $this->assertSame(IssuedTicketStatus::Void->value, DB::table('tickets')->value('status'));
        $this->assertSame(PaymentStatus::Refunded->value, DB::table('orders')->value('payment_status'));
        $this->assertDatabaseHas('fee_ledger_entries', ['kind' => FeeLedgerKind::Owed->value, 'amount_cents' => 150]);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/reports/remittance'))->assertOk()->assertSee('Invoice opened');
        $invoice = DB::table('remittance_invoices')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(150, (int) $invoice->amount_cents);
        $this->assertSame('open', $invoice->status);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/reports/remittance/'.$invoice->id.'/paid'), [
            'payment_reference' => 'check-100',
        ])->assertOk()->assertSee('remitted');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/reports/remittance/'.$invoice->id.'/paid'), [
            'payment_reference' => 'check-100',
        ])->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/reports/remittance/'.$invoice->id.'/paid'), [
            'payment_reference' => 'check-999',
        ])->assertStatus(422);

        $this->assertSame('paid', DB::table('remittance_invoices')->value('status'));
        $this->assertSame(1, DB::table('fee_ledger_entries')->where('kind', FeeLedgerKind::Remitted->value)->count());
        $this->assertDatabaseHas('fee_ledger_entries', [
            'kind' => FeeLedgerKind::Remitted->value,
            'amount_cents' => 150,
        ]);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/reports/finance.csv'))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('150');
    }

    public function test_invalid_credentials_are_not_stored(): void
    {
        $workspace = $this->workspace('alpha.test');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/payments'), [
            'environment' => 'sandbox',
            'api_login_id' => 'invalid',
            'transaction_key' => 'super-secret-key',
            'signature_key' => 'signature-key',
            'public_client_key' => 'public-client-key',
        ])->assertStatus(422)->assertDontSee('super-secret-key');

        $this->assertSame(0, DB::table('payment_settings')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutBody(string $ticketId, string $nonce): array
    {
        return [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'data_descriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
            'data_value' => $nonce,
            'lines' => [
                $ticketId => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ];
    }

    private function saveSandbox(User $user, string $host): void
    {
        $this->actingAs($user)->post($this->hostUrl($host, '/payments'), [
            'environment' => 'sandbox',
            'api_login_id' => 'login-id-4242',
            'transaction_key' => 'super-secret-key',
            'signature_key' => 'signature-key',
            'public_client_key' => 'public-client-key',
        ])->assertOk()->assertSee('validated')->assertDontSee('super-secret-key');
    }

    /**
     * @return array{event: Event, ticket: TicketType}
     */
    private function paidEvent(string $organizationId, int $quantity = 10): array
    {
        app(OrganizationManager::class)->set(
            Organization::query()->whereKey($organizationId)->firstOrFail(),
        );

        $event = Event::query()->create([
            'organization_id' => $organizationId,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'summary' => 'A quartet',
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 03:00:00',
            'published_at' => now(),
        ]);
        $ticket = TicketType::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $event->id,
            'name' => 'Reserved',
            'kind' => TicketKind::Paid,
            'price_cents' => 5000,
            'currency' => 'USD',
            'quantity_total' => $quantity,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);

        return ['event' => $event, 'ticket' => $ticket];
    }
}
