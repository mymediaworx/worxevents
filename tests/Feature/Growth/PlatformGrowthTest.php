<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Mail\AnnouncementMail;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformGrowthTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_suspend_and_reinstate_an_organization(): void
    {
        $workspace = $this->workspace('alpha.test');
        $admin = $this->platformAdmin();
        $support = $this->platformAdmin('platform_support_admin');

        $this->actingAs($support)
            ->post($this->hostUrl('app.worxevents.test', '/platform/organizations/'.$workspace['organization']->id.'/status'), [
                'status' => 'suspended',
            ])->assertForbidden();

        $this->actingAs($admin)
            ->post($this->hostUrl('app.worxevents.test', '/platform/organizations/'.$workspace['organization']->id.'/status'), [
                'status' => 'suspended',
            ])->assertOk()->assertSee('suspended');

        $this->get($this->hostUrl('alpha.test', '/'))->assertForbidden();

        $this->actingAs($admin)
            ->post($this->hostUrl('app.worxevents.test', '/platform/organizations/'.$workspace['organization']->id.'/status'), [
                'status' => 'active',
            ])->assertOk()->assertSee('active');

        $this->get($this->hostUrl('alpha.test', '/'))->assertOk();
        $this->assertSame(OrganizationStatus::Active->value, DB::table('organizations')->where('id', $workspace['organization']->id)->value('status'));
    }

    public function test_plans_payments_disputes_jobs_and_security_stay_in_the_admin_panel(): void
    {
        $workspace = $this->workspace('alpha.test');
        $admin = $this->platformAdmin();
        $support = $this->platformAdmin('platform_support_admin');
        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/payments'), [
            'environment' => 'sandbox',
            'api_login_id' => 'login-id-4242',
            'transaction_key' => 'super-secret-key',
            'signature_key' => 'signature-key',
            'public_client_key' => 'public-client-key',
        ])->assertOk();

        $this->actingAs($support)->get($this->hostUrl('app.worxevents.test', '/platform/plans'))->assertOk();
        $this->actingAs($support)->post($this->hostUrl('app.worxevents.test', '/platform/plans'), [
            'name' => 'Starter',
            'amount_cents' => 0,
        ])->assertForbidden();
        $this->actingAs($admin)->post($this->hostUrl('app.worxevents.test', '/platform/plans'), [
            'name' => 'Starter',
            'description' => 'Free registration',
            'amount_cents' => 0,
            'active' => '1',
        ])->assertOk()->assertSee('Starter')->assertSee('does not charge');

        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/payments'))
            ->assertOk()->assertSee('sandbox')->assertSee('validated')->assertDontSee('super-secret-key');

        $this->actingAs($admin)->post($this->hostUrl('app.worxevents.test', '/platform/disputes'), [
            'for_organization' => $workspace['organization']->id,
            'summary' => 'Buyer asked for a review',
        ])->assertOk()->assertSee('Dispute opened');
        $dispute = (string) DB::table('disputes')->value('id');
        $this->actingAs($admin)->post($this->hostUrl('app.worxevents.test', '/platform/disputes/'.$dispute), [
            'status' => 'won',
        ])->assertOk()->assertSee('won');

        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'SECRET-STACK',
            'failed_at' => now(),
        ]);
        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/jobs'))
            ->assertOk()->assertSee($uuid)->assertDontSee('SECRET-STACK');

        $this->flushSession();
        $this->actingAsGuest();
        $this->post($this->hostUrl('alpha.test', '/login'), [
            'email' => $workspace['user']->email,
            'password' => 'not-the-password',
        ])->assertStatus(422);
        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/security'))
            ->assertOk()->assertSee('Sign-in was rejected.')->assertDontSee('not-the-password');

        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/health'))
            ->assertOk()->assertSee('database ok');
        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/users'))
            ->assertOk()->assertSee($admin->email);
        $this->actingAs($admin)->get($this->hostUrl('app.worxevents.test', '/platform/audit'))
            ->assertOk()->assertSee('auth.login_failed');
    }

    public function test_support_cases_can_be_opened_and_closed(): void
    {
        $workspace = $this->workspace('alpha.test');
        $admin = $this->platformAdmin();

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/support'), [
            'subject' => 'Door scanner',
            'body' => 'The camera prompt did not appear.',
        ])->assertOk()->assertSee('Support case opened');

        $case = (string) DB::table('support_cases')->value('id');
        $this->actingAs($admin)
            ->post($this->hostUrl('app.worxevents.test', '/platform/support/'.$case.'/close'))
            ->assertOk()->assertSee('closed');
        $this->assertSame('closed', DB::table('support_cases')->value('status'));
    }

    public function test_referral_and_utm_are_stored_on_the_order(): void
    {
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id);

        $this->get($this->hostUrl('alpha.test', '/e/jazz-night?utm_source=newsletter&utm_medium=email&utm_campaign=fall&ref=spring'))
            ->assertOk();
        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'lines' => [
                $event['ticket']->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'referral_code' => 'spring',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'fall',
        ]);
    }

    public function test_promo_qr_announcements_and_analytics_respect_consent(): void
    {
        Mail::fake();
        $workspace = $this->workspace('alpha.test');
        $event = $this->event($workspace['organization']->id);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/events/'.$event['event']->id.'/promo?ref=spring'))
            ->assertOk()->assertSee('ref=spring', false)->assertSee('<svg', false);

        $this->post($this->hostUrl('alpha.test', '/e/jazz-night/checkout'), [
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'email_consent' => '1',
            'lines' => [
                $event['ticket']->id => [
                    'quantity' => 1,
                    'attendees' => [
                        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ],
                ],
            ],
        ])->assertOk();

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/announcements'), [
            'subject' => 'Tomorrow',
            'body' => 'Doors at six.',
        ])->assertOk()->assertSee('Sent to 1');

        $url = '';
        Mail::assertSent(AnnouncementMail::class, function (AnnouncementMail $mail) use (&$url): bool {
            $url = $mail->unsubscribeUrl;

            return $mail->hasTo('ada@example.com');
        });
        $this->assertNotSame('', $url);
        $this->get($url)->assertOk()->assertSee('Unsubscribed');

        $this->actingAs($workspace['user'])->post($this->hostUrl('alpha.test', '/announcements'), [
            'subject' => 'Again',
            'body' => 'This should not send.',
        ])->assertOk()->assertSee('Sent to 0');
        Mail::assertSent(AnnouncementMail::class, 1);

        config(['worxevents.analytics_hook' => 'https://example.test/collect.js']);
        $this->get($this->hostUrl('alpha.test', '/about'))->assertOk()->assertDontSee('example.test/collect.js', false);
        $this->withCookie('worxevents_analytics', '1')
            ->get($this->hostUrl('alpha.test', '/about'))
            ->assertOk()->assertSee('https://example.test/collect.js', false);
    }

    /**
     * @return array{event: Event, ticket: TicketType}
     */
    private function event(string $organizationId): array
    {
        app(OrganizationManager::class)->set(Organization::query()->whereKey($organizationId)->firstOrFail());
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
            'name' => 'General',
            'kind' => TicketKind::Free,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity_total' => 10,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);

        return ['event' => $event, 'ticket' => $ticket];
    }
}
