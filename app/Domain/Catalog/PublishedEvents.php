<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Models\Event;
use App\Models\OrganizationDomain;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;

final class PublishedEvents
{
    /**
     * Published events across organizations. Relations that carry the tenant
     * scope are loaded explicitly so the marketplace can read them on a platform host.
     *
     * @return Builder<Event>
     */
    public function query(): Builder
    {
        return Event::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', EventStatus::Published)
            ->whereHas('organization', function (Builder $query): void {
                $query->whereIn('status', [
                    OrganizationStatus::Onboarding->value,
                    OrganizationStatus::Active->value,
                ]);
            })
            ->with([
                'organization.domains',
                'venue' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                'category' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                'ticketTypes' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class)->orderBy('sort'),
            ]);
    }

    /**
     * @return Builder<Event>
     */
    public function upcoming(): Builder
    {
        return $this->query()
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at');
    }

    public function url(Event $event): string
    {
        $event->loadMissing('organization.domains');
        $domain = $event->organization?->domains?->first(
            fn (OrganizationDomain $item): bool => $item->is_primary && $item->verified_at !== null,
        );
        $scheme = app()->environment('production') ? 'https' : request()->getScheme();

        if ($domain instanceof OrganizationDomain) {
            return $scheme.'://'.$domain->hostname.'/e/'.$event->slug;
        }

        return url('/o/'.($event->organization->slug ?? 'organizer').'/e/'.$event->slug);
    }
}
