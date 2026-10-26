<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Mail\TicketDelivery;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Seat;
use App\Models\TicketType;
use App\Models\User;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdvancedCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_series_stops_at_the_event_end(): void
    {
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id, '2026-11-01 18:00:00', '2026-11-20 23:00:00');
        EventSession::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $event->id,
            'name' => 'Opening',
            'starts_at' => '2026-11-01 18:00:00',
            'ends_at' => '2026-11-01 19:00:00',
        ]);

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/series'), [
                'frequency' => 'weekly',
                'until' => '2026-11-16',
            ])->assertOk()->assertSee('Created 2 timed sessions');

        $this->assertSame(2, DB::table('event_sessions')->where('entry_mode', 'timed')->count());
        $this->assertSame(0, DB::table('event_sessions')->where('starts_at', '>', '2026-11-20 23:00:00')->count());
        $this->assertSame('weekly', DB::table('events')->value('recurrence_frequency'));
    }

    public function test_discounts_honor_percent_fixed_expiry_limits_and_eligibility(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->sandbox($workspace['user']);
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 5000);
        $other = $this->ticket($workspace['organization']->id, $event->id, 5000, 'other');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/discounts'), [
            'code' => 'tenoff',
            'kind' => 'fixed',
            'amount' => 1000,
        ])->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/discounts'), [
            'code' => 'take10',
            'kind' => 'percent',
            'amount' => 1000,
            'max_redemptions' => 1,
        ])->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/discounts'), [
            'code' => 'old',
            'kind' => 'fixed',
            'amount' => 100,
            'expires_at' => '2020-01-01',
        ])->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/discounts'), [
            'code' => 'vip',
            'kind' => 'fixed',
            'amount' => 100,
            'ticket_type_id' => $other->id,
        ])->assertOk();

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'old'))
            ->assertStatus(422)->assertSee('expired');
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'vip'))
            ->assertStatus(422)->assertSee('does not apply');

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'TENOFF'))
            ->assertOk()->assertSee('41.20');
        $fixed = DB::table('orders')->where('discount_code', 'TENOFF')->first();
        $this->assertNotNull($fixed);
        $this->assertSame(1000, (int) $fixed->discount_cents);
        $this->assertSame(120, (int) $fixed->fee_cents);
        $this->assertSame(4120, (int) $fixed->total_cents);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'take10', 'Bea'))
            ->assertOk();
        $percent = DB::table('orders')->where('discount_code', 'TAKE10')->first();
        $this->assertNotNull($percent);
        $this->assertSame(500, (int) $percent->discount_cents);
        $this->assertSame(135, (int) $percent->fee_cents);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'take10', 'Cam'))
            ->assertStatus(422)->assertSee('limit');
        $this->assertSame(1, (int) DB::table('discount_codes')->where('code', 'TAKE10')->value('redeemed_count'));
    }

    public function test_add_ons_do_not_change_the_booking_fee_and_a_decline_releases_them(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->sandbox($workspace['user']);
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 5000);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/add-ons'), [
            'name' => 'Poster',
            'price_cents' => 1000,
            'quantity_total' => 5,
        ])->assertOk();
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/discounts'), [
            'code' => 'save5',
            'kind' => 'fixed',
            'amount' => 500,
        ])->assertOk();

        $addOnId = (string) DB::table('add_ons')->value('id');
        $paid = $this->body($ticket->id, null, 'Ada');
        $paid['add_ons'] = [$addOnId => 1];
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $paid)
            ->assertOk()->assertSee('Poster')->assertSee('61.50');

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame(150, (int) $order->fee_cents);
        $this->assertSame(6150, (int) $order->total_cents);
        $this->assertDatabaseHas('fee_snapshots', [
            'order_key' => $order->id,
            'eligible_cents' => 5000,
            'add_on_cents' => 1000,
            'fee_cents' => 150,
        ]);

        $declined = $this->body($ticket->id, 'save5', 'Bea', 'decline-token');
        $declined['add_ons'] = [$addOnId => 1];
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $declined)
            ->assertStatus(422)->assertSee('declined');

        $this->assertSame(1, (int) DB::table('add_ons')->value('quantity_sold'));
        $this->assertSame(0, (int) DB::table('discount_codes')->where('code', 'SAVE5')->value('redeemed_count'));
        $this->assertSame(1, DB::table('order_add_ons')->count());
    }

    public function test_a_full_discount_completes_without_calling_the_processor(): void
    {
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 5000);
        app(OrganizationManager::class)->set($workspace['organization']);
        DB::table('discount_codes')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $workspace['organization']->id,
            'event_id' => $event->id,
            'code' => 'FREE',
            'kind' => 'percent',
            'amount' => 10000,
            'redeemed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'FREE', 'Ada', ''))
            ->assertOk()->assertSee('0.00');

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame(0, (int) $order->total_cents);
        $this->assertSame(0, (int) $order->fee_cents);
        $this->assertNull($order->provider);
        $this->assertSame(PaymentStatus::None->value, $order->payment_status);
        $this->assertSame(1, DB::table('tickets')->count());
        $this->assertDatabaseMissing('fee_ledger_entries', ['kind' => 'owed']);
    }

    public function test_seats_holds_and_the_accessible_picker_have_no_visual_designer(): void
    {
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 0, 'ga', TicketKind::Free);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/events/'.$event->id.'/extras'))
            ->assertOk()->assertSee('A visual designer is not included.');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/seating/plan'), [
            'name' => 'Hall',
        ])->assertOk()->assertSee('A visual designer is not included.');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/seating/sections'), [
            'name' => 'Orchestra',
            'price_zone' => 'A',
        ])->assertOk();
        $sectionId = (string) DB::table('seating_sections')->value('id');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/seating/rows'), [
            'seating_section_id' => $sectionId,
            'label' => 'A',
        ])->assertOk();
        $rowId = (string) DB::table('seating_rows')->value('id');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/seating/seats'), [
            'seating_row_id' => $rowId,
            'label' => '1',
            'accessible' => '1',
        ])->assertOk()->assertSee('(accessible)', false);
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/seating/seats'), [
            'seating_row_id' => $rowId,
            'label' => '2',
        ])->assertOk();

        $this->get($this->hostUrl('alpha.test', '/e/'.$event->slug))
            ->assertOk()->assertSee('(accessible)', false)->assertSee('Orchestra');

        $accessible = (string) DB::table('seats')->where('label', '1')->value('id');
        $other = (string) DB::table('seats')->where('label', '2')->value('id');
        app(OrganizationManager::class)->set($workspace['organization']);
        Seat::query()->whereKey($other)->update([
            'status' => 'held',
            'held_until' => now()->subMinute(),
        ]);

        $this->artisan('worxevents:seats:release')->expectsOutputToContain('released=1')->assertSuccessful();
        $this->assertSame('available', DB::table('seats')->where('id', $other)->value('status'));

        $first = $this->body($ticket->id, null, 'Ada', '');
        $first['seats'] = [$accessible];
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $first)->assertOk();
        $this->assertSame($accessible, DB::table('tickets')->value('seat_id'));

        $second = $this->body($ticket->id, null, 'Bea', '');
        $second['seats'] = [$accessible];
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $second)
            ->assertStatus(409)->assertSee('not available');
    }

    public function test_waitlist_is_unique_and_can_be_offered(): void
    {
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/waitlist'), [
            'name' => 'Ada Lovelace',
            'email' => 'Ada@Example.com',
        ])->assertOk()->assertSee('on the waitlist');
        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/waitlist'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])->assertStatus(422)->assertSee('already on the waitlist');

        $entry = (string) DB::table('waitlist_entries')->value('id');
        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/waitlist/'.$entry.'/offer'))
            ->assertOk()->assertSee('offered');
        $this->assertSame('offered', DB::table('waitlist_entries')->value('status'));
    }

    public function test_transfer_voids_the_old_token(): void
    {
        Mail::fake();
        $workspace = $this->workspace('alpha.test');
        $staff = User::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $workspace['organization']->id,
            'user_id' => $staff->id,
            'role_id' => Role::query()->where('slug', 'check_in_staff')->value('id'),
        ]);
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 0, 'ga', TicketKind::Free);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, null, 'Ada', ''))
            ->assertOk();
        $issued = (string) DB::table('tickets')->value('id');
        $old = '';
        Mail::assertSent(TicketDelivery::class, function (TicketDelivery $mail) use (&$old): bool {
            $old = $mail->passes[0]->token;

            return true;
        });

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/tickets/'.$issued.'/transfer'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ])->assertOk()->assertSee('previous token is void');

        $fresh = '';
        Mail::assertSent(TicketDelivery::class, function (TicketDelivery $mail) use (&$fresh): bool {
            $fresh = $mail->passes[0]->token;

            return $mail->hasTo('grace@example.com');
        });
        $this->assertNotSame($old, $fresh);
        $this->assertSame(IssuedTicketStatus::Void->value, DB::table('tickets')->where('token_hash', hash('sha256', $old))->value('status'));

        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $event->id,
            'token' => $old,
        ])->assertStatus(409)->assertSee('no longer valid');
        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $event->id,
            'token' => $fresh,
        ])->assertOk()->assertSee('Checked in Grace Hopper');
    }

    public function test_reports_include_advanced_totals(): void
    {
        $workspace = $this->workspace('alpha.test');
        $this->sandbox($workspace['user']);
        $event = $this->event($workspace['organization']->id);
        $ticket = $this->ticket($workspace['organization']->id, $event->id, 5000);
        app(OrganizationManager::class)->set($workspace['organization']);
        DB::table('discount_codes')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $workspace['organization']->id,
            'event_id' => $event->id,
            'code' => 'TEN',
            'kind' => 'fixed',
            'amount' => 1000,
            'redeemed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post($this->hostUrl('alpha.test', '/e/'.$event->slug.'/checkout'), $this->body($ticket->id, 'TEN'))->assertOk();
        $this->actingAs($workspace['user'])->get($this->hostUrl('alpha.test', '/reports'))
            ->assertOk()->assertSee('Discounts')->assertSee('10.00');
    }

    private function sandbox(User $user): void
    {
        $this->actingAs($user)->post($this->hostUrl('alpha.test', '/payments'), [
            'environment' => 'sandbox',
            'api_login_id' => 'login-id-4242',
            'transaction_key' => 'super-secret-key',
            'signature_key' => 'signature-key',
            'public_client_key' => 'public-client-key',
        ])->assertOk();
    }

    private function event(string $organizationId, string $starts = '2026-11-02 00:00:00', string $ends = '2026-11-02 03:00:00'): Event
    {
        app(OrganizationManager::class)->set(Organization::query()->whereKey($organizationId)->firstOrFail());

        return Event::query()->create([
            'organization_id' => $organizationId,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'summary' => 'A quartet',
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'published_at' => now(),
        ]);
    }

    private function ticket(string $organizationId, string $eventId, int $price, string $name = 'Reserved', TicketKind $kind = TicketKind::Paid): TicketType
    {
        return TicketType::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'name' => $name,
            'kind' => $price === 0 ? TicketKind::Free : $kind,
            'price_cents' => $price,
            'currency' => 'USD',
            'quantity_total' => 10,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $ticketId, ?string $code, string $name = 'Ada Lovelace', string $nonce = 'opaque-token-value'): array
    {
        $body = [
            'guest_name' => $name,
            'guest_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'lines' => [
                $ticketId => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com'],
                    ],
                ],
            ],
        ];

        if ($code !== null) {
            $body['discount_code'] = $code;
        }

        if ($nonce !== '') {
            $body['data_descriptor'] = 'COMMON.ACCEPT.INAPP.PAYMENT';
            $body['data_value'] = $nonce;
        }

        return $body;
    }
}
