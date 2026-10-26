<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Organizations\OrganizationProvisioner;
use App\Tenancy\OrganizationManager;
use Illuminate\Console\Command;

final class SeedDemoCatalog extends Command
{
    protected $signature = 'worxevents:demo';

    protected $description = 'Create the demo organizer, a free event, and a paid event with card processing left off.';

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

        try {
            $this->event(
                $organization,
                'Demo Community Night',
                'demo-community-night',
                'A free sample registration. No card is required.',
                TicketKind::Free,
                0,
                100,
            );
            $this->event(
                $organization,
                'Demo Paid Showcase',
                'demo-paid-showcase',
                'A paid sample. Authorize.net sandbox is not configured, so checkout cannot charge a card.',
                TicketKind::Paid,
                2500,
                40,
            );
        } finally {
            $organizations->forget();
        }

        $this->line('demo_organization=demo');
        $this->line('demo_host='.$result['hostname']);
        $this->line('demo_events=demo-community-night,demo-paid-showcase');
        $this->line('card_processor=disabled');

        return self::SUCCESS;
    }

    private function event(
        Organization $organization,
        string $title,
        string $slug,
        string $summary,
        TicketKind $kind,
        int $priceCents,
        int $quantity,
    ): void {
        $event = Event::query()->where('slug', $slug)->first();
        $start = now()->addDays(45)->startOfHour();
        $end = $start->copy()->addHours(3);

        if (! $event instanceof Event) {
            $event = Event::query()->create([
                'organization_id' => $organization->id,
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'description' => $summary,
                'status' => EventStatus::Published,
                'timezone' => 'America/New_York',
                'starts_at' => $start,
                'ends_at' => $end,
                'capacity' => $quantity,
                'published_at' => now(),
                'seo_title' => $title,
                'seo_description' => $summary,
            ]);
        } elseif ($event->ends_at < now()) {
            $event->forceFill([
                'status' => EventStatus::Published,
                'starts_at' => $start,
                'ends_at' => $end,
                'published_at' => $event->published_at ?? now(),
            ])->save();
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
            'kind' => $kind,
            'description' => $summary,
            'price_cents' => $priceCents,
            'currency' => 'USD',
            'quantity_total' => $quantity,
            'min_per_order' => 1,
            'max_per_order' => 4,
            'visibility' => TicketVisibility::Public,
            'sort' => 1,
        ]);
    }
}
