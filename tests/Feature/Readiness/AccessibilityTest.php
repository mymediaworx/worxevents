<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Enums\EventStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\Seat;
use App\Models\SeatingPlan;
use App\Models\SeatingRow;
use App\Models\SeatingSection;
use App\Models\TicketType;
use App\Tenancy\OrganizationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_door_pages_expose_names_labels_and_reduced_motion(): void
    {
        $css = file_get_contents(public_path('css/worxevents.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('focus-visible', $css);

        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $event = Event::query()->create([
            'organization_id' => $workspace['organization']->id,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'status' => EventStatus::Published,
            'timezone' => 'UTC',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 03:00:00',
            'published_at' => now(),
        ]);
        TicketType::query()->create([
            'organization_id' => $workspace['organization']->id,
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
        $plan = SeatingPlan::query()->create([
            'organization_id' => $workspace['organization']->id,
            'event_id' => $event->id,
            'name' => 'Hall',
        ]);
        $section = SeatingSection::query()->create([
            'organization_id' => $workspace['organization']->id,
            'seating_plan_id' => $plan->id,
            'name' => 'Orchestra',
            'price_zone' => 'A',
        ]);
        $row = SeatingRow::query()->create([
            'organization_id' => $workspace['organization']->id,
            'seating_section_id' => $section->id,
            'label' => 'A',
        ]);
        Seat::query()->create([
            'organization_id' => $workspace['organization']->id,
            'seating_row_id' => $row->id,
            'label' => '1',
            'accessible' => true,
            'status' => 'available',
        ]);

        foreach ([
            $this->hostUrl('app.worxevents.test', '/about'),
            $this->hostUrl('alpha.test', '/login'),
            $this->hostUrl('alpha.test', '/e/jazz-night'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<html lang="en">', false)
                ->assertSee('href="#content"', false)
                ->assertSee('id="content"', false);
        }

        $this->get($this->hostUrl('alpha.test', '/login'))
            ->assertSee('for="email"', false)
            ->assertSee('for="password"', false)
            ->assertSee('>Sign in<', false);

        $this->get($this->hostUrl('alpha.test', '/e/jazz-night'))
            ->assertSee('(accessible)', false)
            ->assertSee('name="guest_name"', false);

        $this->actingAs($workspace['user'])
            ->get($this->hostUrl('alpha.test', '/check-in'))
            ->assertOk()
            ->assertSee('href="#content"', false)
            ->assertSee('name="token"', false)
            ->assertSee('BarcodeDetector', false);
    }
}
