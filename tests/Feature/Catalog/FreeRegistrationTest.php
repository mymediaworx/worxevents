<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\CheckoutRejected;
use App\Domain\Catalog\InventoryBook;
use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Enums\QuestionField;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Mail\TicketDelivery;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventSession;
use App\Models\TicketType;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class FreeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_free_registration_issues_hashed_tickets_and_a_zero_fee(): void
    {
        Mail::fake();
        $workspace = $this->workspace('alpha.test');
        $built = $this->sellableEvent($workspace['organization']->id);
        $question = EventQuestion::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $built['event']->id,
            'label' => 'Meal',
            'field_type' => QuestionField::Text,
            'required' => true,
            'sort' => 0,
        ]);

        $this->post($this->hostUrl('alpha.test', '/e/'.$built['event']->slug.'/checkout'), [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'lines' => [
                $built['ticket']->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ])->assertStatus(422);

        $response = $this->post($this->hostUrl('alpha.test', '/e/'.$built['event']->slug.'/checkout'), [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'Ada@Example.com',
            'answers' => [$question->id => 'Vegetarian'],
            'lines' => [
                $built['ticket']->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertSee("You're registered", false)->assertSee('noindex', false);

        $mail = null;
        Mail::assertSent(TicketDelivery::class, function (TicketDelivery $sent) use (&$mail): bool {
            $mail = $sent;

            return $sent->hasTo('ada@example.com');
        });
        $this->assertInstanceOf(TicketDelivery::class, $mail);
        $token = $mail->passes[0]->token;
        $this->assertSame(64, strlen($token));
        $this->assertStringNotContainsString($token, $response->getContent());
        $this->assertStringNotContainsString($token, $mail->render());
        $this->assertStringStartsWith('%PDF', $mail->passes[0]->pdf);
        $this->assertStringContainsString($token, $mail->passes[0]->pdf);
        $this->assertStringContainsString('<svg', $mail->passes[0]->svg);
        $this->assertStringNotContainsString($token, $mail->passes[0]->svg);

        $this->assertDatabaseHas('tickets', ['token_hash' => hash('sha256', $token), 'status' => 'issued']);
        $this->assertDatabaseMissing('tickets', ['token_hash' => $token]);
        $orderId = (string) DB::table('orders')->where('guest_email', 'ada@example.com')->value('id');
        $this->assertDatabaseHas('fee_snapshots', [
            'order_key' => $orderId,
            'fee_cents' => 0,
            'ticket_subtotal_cents' => 0,
        ]);
        $this->assertDatabaseHas('fee_ledger_entries', [
            'order_key' => $orderId,
            'kind' => 'assessed',
            'amount_cents' => 0,
        ]);
        $this->assertDatabaseHas('order_lines', [
            'order_id' => $orderId,
            'name_snapshot' => 'General admission',
            'unit_price_cents' => 0,
        ]);
        $this->assertDatabaseHas('order_answers', [
            'order_id' => $orderId,
            'value' => 'Vegetarian',
        ]);
        $this->assertStringNotContainsString($token, (string) DB::table('audit_logs')->where('action', 'order.completed')->value('properties'));

        $built['ticket']->update(['name' => 'Renamed']);
        $this->assertDatabaseHas('order_lines', ['name_snapshot' => 'General admission']);
        $this->assertSame(1, DB::table('fee_snapshots')->count());

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/reports'))
            ->assertOk()
            ->assertSee('Completed registrations: 1');

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/orders/'.$orderId.'/reissue'))
            ->assertRedirect();

        $second = null;
        Mail::assertSent(TicketDelivery::class, 2);
        $sent = Mail::sent(TicketDelivery::class);
        $second = $sent->last();
        $this->assertInstanceOf(TicketDelivery::class, $second);
        $this->assertDatabaseHas('tickets', ['token_hash' => hash('sha256', $token), 'status' => 'void']);
        $this->assertDatabaseHas('tickets', ['token_hash' => hash('sha256', $second->passes[0]->token), 'status' => 'issued']);
    }

    public function test_paid_tickets_can_be_stored_but_not_checked_out(): void
    {
        $workspace = $this->workspace('alpha.test');
        $built = $this->sellableEvent($workspace['organization']->id);
        $paid = TicketType::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $built['event']->id,
            'name' => 'Reserved seat',
            'kind' => TicketKind::Paid,
            'price_cents' => 2500,
            'currency' => 'USD',
            'quantity_total' => 10,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);

        $this->post($this->hostUrl('alpha.test', '/e/'.$built['event']->slug.'/checkout'), [
            'guest_name' => 'Grace Hopper',
            'guest_email' => 'grace@example.com',
            'lines' => [
                $paid->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Grace Hopper', 'email' => 'grace@example.com'],
                    ],
                ],
            ],
        ])->assertStatus(422)->assertSee('card processor');

        $this->assertSame(0, DB::table('orders')->count());
    }

    public function test_holds_expire_and_cannot_oversell(): void
    {
        Carbon::setTestNow('2026-10-26 12:00:00');
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $built = $this->sellableEvent($workspace['organization']->id, 1);
        $book = app(InventoryBook::class);

        $hold = $book->reserve($built['ticket'], 1);
        $this->assertSame(1, $built['ticket']->fresh()?->quantity_held);

        try {
            $book->reserve($built['ticket']->fresh() ?? $built['ticket'], 1);
            $this->fail('A second hold should have been refused.');
        } catch (CheckoutRejected $exception) {
            $this->assertSame(409, $exception->status);
        }

        Carbon::setTestNow('2026-10-26 12:16:00');
        $this->artisan('worxevents:reservations:release')->expectsOutputToContain('released=1')->assertSuccessful();
        $this->assertSame(0, $built['ticket']->fresh()?->quantity_held);
        $this->assertNotNull(DB::table('inventory_reservations')->where('hold_token_hash', hash('sha256', $hold->token))->value('released_at'));

        $again = $book->reserve($built['ticket']->fresh() ?? $built['ticket'], 1);
        $this->assertNotSame($hold->token, $again->token);
    }

    public function test_event_and_session_capacity_are_enforced(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $built = $this->sellableEvent($workspace['organization']->id, 5, 1);
        $book = app(InventoryBook::class);
        $book->reserve($built['ticket'], 1);

        try {
            $book->reserve($built['ticket']->fresh() ?? $built['ticket'], 1);
            $this->fail('Event capacity should have stopped the second hold.');
        } catch (CheckoutRejected $exception) {
            $this->assertSame(409, $exception->status);
        }

        $session = EventSession::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $built['event']->id,
            'name' => 'Evening',
            'starts_at' => $built['event']->starts_at,
            'ends_at' => $built['event']->ends_at,
            'capacity' => 1,
        ]);
        $sessionTicket = TicketType::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $built['event']->id,
            'session_id' => $session->id,
            'name' => 'Evening admission',
            'kind' => TicketKind::Free,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity_total' => 10,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);
        $built['event']->update(['capacity' => null]);
        $book->reserve($sessionTicket, 1);

        try {
            $book->reserve($sessionTicket->fresh() ?? $sessionTicket, 1);
            $this->fail('Session capacity should have stopped the second hold.');
        } catch (CheckoutRejected $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    public function test_setup_saves_drafts_and_finishes_onboarding(): void
    {
        $workspace = $this->workspace('alpha.test');
        $workspace['organization']->update(['status' => OrganizationStatus::Onboarding]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->assertIsString($png);

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/setup/locale'), [
                'timezone' => 'America/Chicago',
                'currency' => 'EUR',
            ])->assertStatus(422);

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/setup/details'), [
                'legal_name' => 'Harbor Events LLC',
                'support_email' => 'help@harbor.test',
                'description' => 'Live music.',
            ])->assertRedirect();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/setup/branding'), [
                'brand_accent' => '#0C5C4E',
                'logo' => UploadedFile::fake()->createWithContent('logo.png', $png),
            ])->assertRedirect();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/setup/locale'), [
                'timezone' => 'America/Chicago',
                'currency' => 'USD',
            ])->assertRedirect();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/setup/finish'))
            ->assertRedirect();

        $organization = $workspace['organization']->fresh();
        $this->assertNotNull($organization);
        $this->assertSame('Harbor Events LLC', $organization->legal_name);
        $this->assertSame('#0c5c4e', $organization->brand_accent);
        $this->assertNotNull($organization->logo_path);
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertNotNull($organization->setup_completed_at);

        $this->get($this->hostUrl('alpha.test', '/media/logo'))->assertOk();
    }

    /**
     * @return array{event: Event, ticket: TicketType}
     */
    private function sellableEvent(string $organizationId, int $quantity = 25, ?int $capacity = null): array
    {
        $event = Event::query()->create([
            'organization_id' => $organizationId,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'summary' => 'A quartet',
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 03:00:00',
            'capacity' => $capacity,
            'published_at' => now(),
            'seo_title' => 'Jazz Night in Chicago',
            'seo_description' => 'Hear the quartet.',
        ]);
        $ticket = TicketType::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $event->id,
            'name' => 'General admission',
            'kind' => TicketKind::Free,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity_total' => $quantity,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);

        return ['event' => $event, 'ticket' => $ticket];
    }
}
