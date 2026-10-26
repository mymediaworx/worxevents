<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\TicketType;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_hide_drafts_and_describe_published_events(): void
    {
        $workspace = $this->workspace('alpha.test');
        $other = $this->workspace('beta.test');
        $category = EventCategory::query()->create([
            'organization_id' => $workspace['organization']->id,
            'name' => 'Music',
            'slug' => 'music',
        ]);
        $venue = Venue::query()->create([
            'organization_id' => $workspace['organization']->id,
            'name' => 'River Hall',
            'slug' => 'river-hall',
            'city' => 'Chicago',
            'country' => 'US',
        ]);
        $published = Event::query()->create([
            'organization_id' => $workspace['organization']->id,
            'venue_id' => $venue->id,
            'category_id' => $category->id,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'summary' => 'A quartet downtown',
            'status' => EventStatus::Published,
            'timezone' => 'America/Chicago',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
            'published_at' => now(),
            'seo_title' => 'Jazz Night in Chicago',
            'seo_description' => 'Hear the quartet.',
        ]);
        TicketType::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $published->id,
            'name' => 'General admission',
            'kind' => TicketKind::Free,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity_total' => 20,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);
        Event::query()->create([
            'organization_id' => $workspace['organization']->id,
            'title' => 'Secret Draft',
            'slug' => 'secret-draft',
            'status' => EventStatus::Draft,
            'timezone' => 'UTC',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHour(),
        ]);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/events/'.$published->id.'/preview'))
            ->assertOk()
            ->assertSee('noindex', false);

        $this->get($this->hostUrl('alpha.test', '/e/secret-draft'))->assertNotFound();
        $this->get($this->hostUrl('beta.test', '/e/jazz-night'))->assertNotFound();

        $this->get($this->hostUrl('alpha.test', '/e/jazz-night'))
            ->assertOk()
            ->assertSee('property="og:title"', false)
            ->assertSee('Jazz Night in Chicago', false)
            ->assertSee('schema.org', false)
            ->assertSee('"@type":"Event"', false)
            ->assertDontSee('noindex', false);

        $this->get($this->hostUrl('alpha.test', '/'))
            ->assertOk()
            ->assertSee($workspace['organization']->name)
            ->assertSee('Jazz Night')
            ->assertDontSee('Secret Draft');

        $this->get($this->hostUrl('app.worxevents.test', '/browse?q=Jazz&city=Chicago'))
            ->assertOk()
            ->assertSee('Jazz Night')
            ->assertDontSee('Secret Draft');

        $this->get($this->hostUrl('app.worxevents.test', '/categories/music'))
            ->assertOk()
            ->assertSee('Jazz Night');

        $this->get($this->hostUrl('app.worxevents.test', '/o/'.$workspace['organization']->slug))
            ->assertOk()
            ->assertSee('Jazz Night');

        $this->get($this->hostUrl('app.worxevents.test', '/o/'.$workspace['organization']->slug.'/v/river-hall'))
            ->assertOk()
            ->assertSee('River Hall')
            ->assertSee('Jazz Night');

        $this->get($this->hostUrl('alpha.test', '/sitemap.xml'))
            ->assertOk()
            ->assertSee('jazz-night', false)
            ->assertDontSee('secret-draft');

        $this->get($this->hostUrl('app.worxevents.test', '/about'))->assertOk();
        $this->get($this->hostUrl('app.worxevents.test', '/pricing'))->assertOk()->assertSee('Free registrations are $0');
        $this->get($this->hostUrl('app.worxevents.test', '/help'))->assertOk();
        $this->get($this->hostUrl('app.worxevents.test', '/legal/terms'))->assertOk()->assertSee('not legal advice');
        $this->get($this->hostUrl('app.worxevents.test', '/legal/privacy'))->assertOk()->assertSee('not legal advice');
        $this->get($this->hostUrl('app.worxevents.test', '/legal/refunds'))->assertOk()->assertSee('not automatically refunded');
        $this->get($this->hostUrl('app.worxevents.test', '/accessibility'))->assertOk()->assertSee('WCAG 2.2 AA');

        $this->post($this->hostUrl('alpha.test', '/contact'), [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'body' => 'Where is the door?',
        ])->assertRedirect();
        $this->assertDatabaseHas('contact_messages', [
            'organization_id' => $workspace['organization']->id,
            'email' => 'sam@example.com',
        ]);

        $this->actingAs($other['user'])
            ->get($this->hostUrl('beta.test', '/events/'.$published->id.'/edit'))
            ->assertNotFound();

        $this->artisan('schedule:list')->expectsOutputToContain('worxevents:reservations:release')->assertSuccessful();
    }

    public function test_an_organizer_can_draft_and_publish_from_the_event_form(): void
    {
        $workspace = $this->workspace('alpha.test');

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/venues'), [
                'name' => 'River Hall',
                'city' => 'Chicago',
                'country' => 'US',
            ])->assertRedirect();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/events'), [
                'title' => 'Workshop',
                'timezone' => 'UTC',
                'starts_at' => '2026-12-01T15:00',
                'ends_at' => '2026-12-01T17:00',
                'is_multi_day' => '1',
                'category_name' => 'Learning',
                'tags' => 'workshop',
                'summary' => 'Hands on',
            ])->assertRedirect();

        $event = Event::query()->withoutGlobalScopes()->where('title', 'Workshop')->firstOrFail();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/tickets'), [
                'name' => 'Free seat',
                'kind' => 'free',
                'price' => '0.00',
                'quantity_total' => 30,
                'min_per_order' => 1,
                'max_per_order' => 2,
                'visibility' => 'public',
            ])->assertRedirect();

        $this->actingAs($workspace['user'])
            ->post($this->hostUrl('alpha.test', '/events/'.$event->id.'/publish'))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => EventStatus::Published->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.published']);
        $this->get($this->hostUrl('alpha.test', '/e/'.$event->slug))->assertOk()->assertSee('Workshop');
    }
}
