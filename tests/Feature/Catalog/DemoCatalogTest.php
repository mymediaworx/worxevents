<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PaymentSetting;
use App\Models\TicketType;
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

        $this->assertCount(2, $events);
        $this->assertSame(['demo-community-night', 'demo-paid-showcase'], $events->pluck('slug')->all());
        $this->assertSame(0, (int) $events[0]->ticketTypes()->withoutGlobalScope(OrganizationScope::class)->value('price_cents'));
        $this->assertSame(2500, (int) TicketType::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('event_id', $events[1]->id)
            ->value('price_cents'));
        $this->assertSame(0, PaymentSetting::query()->withoutGlobalScope(OrganizationScope::class)->count());
        Notification::assertSentTimes(WorkspacePasswordReset::class, 1);

        $this->get($this->hostUrl('app.worxevents.test', '/'))
            ->assertOk()
            ->assertSee('Demo Community Night')
            ->assertSee('Demo Paid Showcase');

        $this->get($this->hostUrl('demo.worxevents.test', '/e/demo-paid-showcase'))
            ->assertOk()
            ->assertSee('not configured', false);
    }
}
