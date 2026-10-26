<?php

declare(strict_types=1);

namespace Tests\Feature\EventDay;

use App\Domain\Catalog\InventoryBook;
use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Mail\TicketDelivery;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\TicketType;
use App\Models\User;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DoorAndBoxOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_outcomes_and_reversal_permission(): void
    {
        $workspace = $this->workspace('alpha.test');
        $staff = User::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $workspace['organization']->id,
            'user_id' => $staff->id,
            'role_id' => Role::query()->where('slug', 'check_in_staff')->value('id'),
        ]);
        $built = $this->event($workspace['organization']->id, 0, 5);
        $other = Event::query()->create([
            'organization_id' => $workspace['organization']->id,
            'title' => 'Other Night',
            'slug' => 'other-night',
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => '2026-11-03 00:00:00',
            'ends_at' => '2026-11-03 03:00:00',
            'published_at' => now(),
        ]);

        Mail::fake();
        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), [
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
        ])->assertOk();

        $token = '';
        Mail::assertSent(TicketDelivery::class, function (TicketDelivery $mail) use (&$token): bool {
            $token = $mail->passes[0]->token;

            return true;
        });

        $this->actingAs($staff)->get($this->hostUrl('alpha.test', '/check-in'))
            ->assertOk()
            ->assertSee('BarcodeDetector', false)
            ->assertSee('name="token"', false);

        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $built['event']->id,
            'token' => $token,
        ])->assertOk()->assertSee('Checked in Ada Lovelace');

        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $built['event']->id,
            'token' => $token,
        ])->assertStatus(409)->assertSee('already checked in');

        $ticketId = (string) DB::table('tickets')->where('token_hash', hash('sha256', $token))->value('id');

        $this->actingAs($staff)
            ->post($this->hostUrl('alpha.test', '/check-in/tickets/'.$ticketId.'/reverse'))
            ->assertForbidden();
        $this->assertNotNull(DB::table('tickets')->where('id', $ticketId)->value('checked_in_at'));

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/check-in/tickets/'.$ticketId.'/reverse'))
            ->assertOk()
            ->assertSee('reversed');
        $this->assertNull(DB::table('tickets')->where('id', $ticketId)->value('checked_in_at'));

        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $other->id,
            'token' => $token,
        ])->assertStatus(409)->assertSee('different event');

        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/lookup'), [
            'event_id' => $built['event']->id,
            'query' => 'Ada',
        ])->assertOk()->assertSee('Ada Lovelace');

        DB::table('tickets')->where('id', $ticketId)->update(['status' => IssuedTicketStatus::Void->value]);
        DB::table('orders')->update(['payment_status' => PaymentStatus::Refunded->value]);
        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $built['event']->id,
            'token' => $token,
        ])->assertStatus(409)->assertSee('refunded');

        $second = $this->checkout($built['ticket']->id, 'Grace Hopper', 'grace@example.com');
        DB::table('orders')->where('id', $second['order_id'])->update(['status' => 'canceled']);
        $this->actingAs($staff)->post($this->hostUrl('alpha.test', '/check-in/scan'), [
            'event_id' => $built['event']->id,
            'token' => $second['token'],
        ])->assertStatus(409)->assertSee('canceled');

        $this->actingAs($staff)
            ->get($this->hostUrl('alpha.test', '/events/'.$built['event']->id.'/attendees.csv'))
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertDontSee($token);
    }

    public function test_box_office_shares_inventory_and_records_cash_card_and_comps(): void
    {
        $workspace = $this->workspace('alpha.test');
        $built = $this->event($workspace['organization']->id, 2500, 2);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/box-office'), [
            'event_id' => $built['event']->id,
            'ticket_type_id' => $built['ticket']->id,
            'attendee_name' => 'Door Guest',
            'attendee_email' => 'door@example.com',
            'tender' => 'cash',
        ])->assertOk()->assertSee('cash')->assertSee('25.00');

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame('box_office', $order->channel);
        $this->assertSame('cash', $order->tender);
        $this->assertSame(2500, (int) $order->total_cents);
        $this->assertSame(0, (int) $order->fee_cents);
        $this->assertNull($order->provider);
        $this->assertSame(1, DB::table('tickets')->count());
        $this->assertDatabaseHas('fee_ledger_entries', ['kind' => 'assessed', 'amount_cents' => 0]);
        $this->assertDatabaseMissing('fee_ledger_entries', ['kind' => 'owed']);

        app(OrganizationManager::class)->set($workspace['organization']);
        $held = TicketType::query()->whereKey($built['ticket']->id)->firstOrFail();
        app(InventoryBook::class)->reserve($held, 1);

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/box-office'), [
            'event_id' => $built['event']->id,
            'ticket_type_id' => $built['ticket']->id,
            'attendee_name' => 'Second Guest',
            'attendee_email' => 'second@example.com',
            'tender' => 'card',
        ])->assertStatus(409)->assertSee('Not enough tickets');

        $comp = $this->event($workspace['organization']->id, 4000, 2, 'matinee', 'Matinee');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/box-office'), [
            'event_id' => $comp['event']->id,
            'ticket_type_id' => $comp['ticket']->id,
            'attendee_name' => 'Comp Guest',
            'attendee_email' => 'comp@example.com',
            'tender' => 'comp',
        ])->assertOk()->assertSee('comp')->assertSee('0.00');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/box-office'), [
            'event_id' => $comp['event']->id,
            'ticket_type_id' => $comp['ticket']->id,
            'attendee_name' => 'Card Guest',
            'attendee_email' => 'card@example.com',
            'tender' => 'card',
        ])->assertOk()->assertSee('card');

        $card = DB::table('orders')->where('tender', 'card')->first();
        $this->assertNotNull($card);
        $this->assertSame(4000, (int) $card->total_cents);
        $this->assertNull($card->provider);
        $this->assertNull($card->provider_reference);
        $this->assertSame(2, (int) DB::table('ticket_types')->where('id', $comp['ticket']->id)->value('quantity_sold'));
    }

    /**
     * @return array{token: string, order_id: string}
     */
    private function checkout(string $ticketId, string $name, string $email): array
    {
        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), [
            'guest_name' => $name,
            'guest_email' => $email,
            'lines' => [
                $ticketId => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => $name, 'email' => $email],
                    ],
                ],
            ],
        ])->assertOk();

        $token = '';
        Mail::assertSent(TicketDelivery::class, function (TicketDelivery $mail) use (&$token, $name): bool {
            if ($mail->guestName !== $name) {
                return false;
            }

            $token = $mail->passes[0]->token;

            return true;
        });

        return [
            'token' => $token,
            'order_id' => (string) DB::table('orders')->where('guest_email', $email)->value('id'),
        ];
    }

    /**
     * @return array{event: Event, ticket: TicketType}
     */
    private function event(string $organizationId, int $price, int $quantity, string $slug = 'jazz-night', string $title = 'Jazz Night'): array
    {
        app(OrganizationManager::class)->set(
            Organization::query()->whereKey($organizationId)->firstOrFail(),
        );

        $event = Event::query()->create([
            'organization_id' => $organizationId,
            'title' => $title,
            'slug' => $slug,
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 03:00:00',
            'published_at' => now(),
        ]);
        $ticket = TicketType::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $event->id,
            'name' => 'General admission',
            'kind' => $price === 0 ? TicketKind::Free : TicketKind::Paid,
            'price_cents' => $price,
            'currency' => 'USD',
            'quantity_total' => $quantity,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);

        return ['event' => $event, 'ticket' => $ticket];
    }
}
