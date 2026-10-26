<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PaymentSetting;
use App\Models\TicketType;
use App\Models\Venue;
use App\Notifications\WorkspacePasswordReset;
use App\Tenancy\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class DemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_public_and_idempotent_without_a_card_processor(): void
    {
        Notification::fake();

        $this->artisan('worxevents:demo')
            ->expectsOutputToContain('demo_host=demo.worxevents.test')
            ->expectsOutputToContain('card_processor=disabled')
            ->assertSuccessful();

        $this->artisan('worxevents:demo')->assertSuccessful();

        $organization = Organization::query()->where('slug', 'demo')->firstOrFail();
        $events = Event::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderBy('slug')
            ->get();

        $this->assertCount(8, $events);
        $this->assertEqualsCanonicalizing([
            'demo-community-night',
            'demo-paid-showcase',
            'harbor-lights-jazz',
            'morning-market',
            'founders-workshop',
            'river-run',
            'gallery-after-dark',
            'family-film-lawn',
        ], $events->pluck('slug')->all());

        $priceFor = function (string $slug) use ($events): int {
            $eventId = $events->firstWhere('slug', $slug)?->id;

            return (int) TicketType::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('event_id', $eventId)
                ->value('price_cents');
        };

        $this->assertSame(0, $priceFor('demo-community-night'));
        $this->assertSame(2500, $priceFor('demo-paid-showcase'));
        $this->assertSame(4000, $priceFor('harbor-lights-jazz'));
        $this->assertSame(0, PaymentSetting::query()->withoutGlobalScope(OrganizationScope::class)->count());
        Notification::assertSentTimes(WorkspacePasswordReset::class, 1);

        foreach ($events as $event) {
            $this->assertFileExists(public_path('images/demo/'.$event->slug.'.svg'));
        }

        $night = $events->firstWhere('slug', 'demo-community-night');
        $this->assertNotNull($night);
        $venue = Venue::query()->withoutGlobalScope(OrganizationScope::class)->find($night->venue_id);
        $this->assertSame('Riverside Hall', $venue?->name);

        $this->get($this->hostUrl('app.worxevents.test', '/'))
            ->assertOk()
            ->assertSee('Demo Community Night')
            ->assertSee('Demo Paid Showcase')
            ->assertSee('Harbor Lights Jazz')
            ->assertSee('css/worxevents.css', false)
            ->assertSee('images/demo/demo-community-night.svg', false);

        $this->get($this->hostUrl('demo.worxevents.test', '/e/demo-paid-showcase'))
            ->assertOk()
            ->assertSee('not configured', false);
    }
}
