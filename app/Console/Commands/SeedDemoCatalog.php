<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\Organizations\OrganizationProvisioner;
use App\Tenancy\OrganizationManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SeedDemoCatalog extends Command
{
    protected $signature = 'worxevents:demo';

    protected $description = 'Create the demo organizer and a published catalog with card processing left off.';

    public function handle(OrganizationProvisioner $provisioner, OrganizationManager $organizations): int
    {
        $email = 'michael@brandworx.ai';
        $result = $provisioner->apply('demo', 'Demo Organizer', $email, 'America/New_York');
        $organization = $result['organization'];
        $organization->forceFill([
            'legal_name' => 'Demo Organizer',
            'support_email' => $email,
            'currency' => 'USD',
            'description' => 'Sample organizer for the WorxEvents prototype.',
            'status' => OrganizationStatus::Active,
            'setup_step' => 4,
            'setup_completed_at' => $organization->setup_completed_at ?? now(),
        ])->save();

        $organizations->set($organization);
        $slugs = [];

        try {
            foreach ($this->catalog() as $item) {
                $this->event($organization, $item);
                $slugs[] = $item['slug'];
            }
        } finally {
            $organizations->forget();
        }

        $this->line('demo_organization=demo');
        $this->line('demo_host='.$result['hostname']);
        $this->line('demo_events='.implode(',', $slugs));
        $this->line('card_processor=disabled');

        return self::SUCCESS;
    }

    /**
     * @param  array{title: string, slug: string, summary: string, kind: TicketKind, price: int, quantity: int, days: int, category: string, venue: string, city: string}  $item
     */
    private function event(Organization $organization, array $item): void
    {
        $venue = $this->place($organization, $item['venue'], $item['city']);
        $category = $this->category($organization, $item['category']);
        $event = Event::query()->where('slug', $item['slug'])->first();
        $start = now()->addDays($item['days'])->startOfHour();
        $end = $start->copy()->addHours(3);

        if (! $event instanceof Event) {
            $event = Event::query()->create([
                'organization_id' => $organization->id,
                'venue_id' => $venue->id,
                'category_id' => $category->id,
                'title' => $item['title'],
                'slug' => $item['slug'],
                'summary' => $item['summary'],
                'description' => $item['summary'],
                'status' => EventStatus::Published,
                'timezone' => 'America/New_York',
                'starts_at' => $start,
                'ends_at' => $end,
                'capacity' => $item['quantity'],
                'published_at' => now(),
                'seo_title' => $item['title'],
                'seo_description' => $item['summary'],
            ]);
        } else {
            $fill = [];

            if ($event->venue_id === null) {
                $fill['venue_id'] = $venue->id;
            }

            if ($event->category_id === null) {
                $fill['category_id'] = $category->id;
            }

            if ($event->ends_at < now()) {
                $fill['status'] = EventStatus::Published;
                $fill['starts_at'] = $start;
                $fill['ends_at'] = $end;
                $fill['published_at'] = $event->published_at ?? now();
            }

            if ($fill !== []) {
                $event->forceFill($fill)->save();
            }
        }

        $ticket = TicketType::query()
            ->where('event_id', $event->id)
            ->where('name', 'General admission')
            ->first();

        if ($ticket instanceof TicketType) {
            return;
        }

        TicketType::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'name' => 'General admission',
            'kind' => $item['kind'],
            'description' => $item['summary'],
            'price_cents' => $item['price'],
            'currency' => 'USD',
            'quantity_total' => $item['quantity'],
            'min_per_order' => 1,
            'max_per_order' => 4,
            'visibility' => TicketVisibility::Public,
            'sort' => 1,
        ]);
    }

    private function place(Organization $organization, string $name, string $city): Venue
    {
        $slug = Str::slug($name);
        $venue = Venue::query()->where('slug', $slug)->first();

        if ($venue instanceof Venue) {
            return $venue;
        }

        return Venue::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'city' => $city,
            'region' => 'NY',
            'country' => 'US',
        ]);
    }

    private function category(Organization $organization, string $name): EventCategory
    {
        $slug = Str::slug($name);
        $category = EventCategory::query()->where('slug', $slug)->first();

        if ($category instanceof EventCategory) {
            return $category;
        }

        return EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    /**
     * @return list<array{title: string, slug: string, summary: string, kind: TicketKind, price: int, quantity: int, days: int, category: string, venue: string, city: string}>
     */
    private function catalog(): array
    {
        return [
            [
                'title' => 'Demo Community Night',
                'slug' => 'demo-community-night',
                'summary' => 'A free sample registration. No card is required.',
                'kind' => TicketKind::Free,
                'price' => 0,
                'quantity' => 100,
                'days' => 12,
                'category' => 'Community',
                'venue' => 'Riverside Hall',
                'city' => 'Hudson',
            ],
            [
                'title' => 'Demo Paid Showcase',
                'slug' => 'demo-paid-showcase',
                'summary' => 'A paid sample. Authorize.net sandbox is not configured, so checkout cannot charge a card.',
                'kind' => TicketKind::Paid,
                'price' => 2500,
                'quantity' => 40,
                'days' => 18,
                'category' => 'Arts',
                'venue' => 'BrandWorx Stage',
                'city' => 'Brooklyn',
            ],
            [
                'title' => 'Harbor Lights Jazz',
                'slug' => 'harbor-lights-jazz',
                'summary' => 'An evening set in a small listening room. Card processing is not configured.',
                'kind' => TicketKind::Paid,
                'price' => 4000,
                'quantity' => 80,
                'days' => 22,
                'category' => 'Music',
                'venue' => 'The Listening Room',
                'city' => 'Brooklyn',
            ],
            [
                'title' => 'Morning Market',
                'slug' => 'morning-market',
                'summary' => 'A free Saturday market on the square.',
                'kind' => TicketKind::Free,
                'price' => 0,
                'quantity' => 200,
                'days' => 26,
                'category' => 'Markets',
                'venue' => 'City Square',
                'city' => 'Hudson',
            ],
            [
                'title' => 'Founders Workshop',
                'slug' => 'founders-workshop',
                'summary' => 'A short working session for new organizers. Card processing is not configured.',
                'kind' => TicketKind::Paid,
                'price' => 1500,
                'quantity' => 24,
                'days' => 30,
                'category' => 'Workshops',
                'venue' => 'Studio 4',
                'city' => 'Brooklyn',
            ],
            [
                'title' => 'River Run',
                'slug' => 'river-run',
                'summary' => 'A measured 10K along the river path. Card processing is not configured.',
                'kind' => TicketKind::Paid,
                'price' => 3500,
                'quantity' => 150,
                'days' => 34,
                'category' => 'Sports',
                'venue' => 'Riverfront Park',
                'city' => 'Hudson',
            ],
            [
                'title' => 'Gallery After Dark',
                'slug' => 'gallery-after-dark',
                'summary' => 'A free late opening at the north gallery.',
                'kind' => TicketKind::Free,
                'price' => 0,
                'quantity' => 120,
                'days' => 38,
                'category' => 'Arts',
                'venue' => 'North Gallery',
                'city' => 'Beacon',
            ],
            [
                'title' => 'Family Film Lawn',
                'slug' => 'family-film-lawn',
                'summary' => 'A free outdoor film. Bring a blanket.',
                'kind' => TicketKind::Free,
                'price' => 0,
                'quantity' => 300,
                'days' => 42,
                'category' => 'Family',
                'venue' => 'Civic Lawn',
                'city' => 'Hudson',
            ],
        ];
    }
}
