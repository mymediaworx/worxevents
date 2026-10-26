<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Catalog\EventPageData;
use App\Domain\Catalog\PublishedEvents;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Tenancy\OrganizationScope;
use Illuminate\Http\Response;

final class EventPageController extends Controller
{
    public function __construct(
        private readonly EventPageData $pages,
        private readonly PublishedEvents $published,
    ) {}

    public function organization(string $slug): Response
    {
        $event = Event::query()->where('slug', $slug)->first();

        if (! $event instanceof Event || ! $event->isPubliclyVisible()) {
            abort(404);
        }

        $event->load(['organization.domains', 'venue', 'category', 'tags', 'sessions', 'ticketTypes', 'questions']);

        return response()->view('public.event', $this->pages->for($event));
    }

    public function platform(string $organization, string $event): Response
    {
        $org = Organization::query()
            ->where('slug', $organization)
            ->whereIn('status', [OrganizationStatus::Onboarding, OrganizationStatus::Active])
            ->first();

        if (! $org instanceof Organization) {
            abort(404);
        }

        $model = $this->published->query()
            ->where('organization_id', $org->id)
            ->where('slug', $event)
            ->first();

        if (! $model instanceof Event) {
            $model = Event::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $org->id)
                ->where('slug', $event)
                ->where('status', '!=', 'draft')
                ->with([
                    'organization.domains',
                    'venue' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                    'category' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                    'tags' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                    'sessions' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                    'ticketTypes' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                    'questions' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                ])
                ->first();
        }

        if (! $model instanceof Event || ! $model->isPubliclyVisible()) {
            abort(404);
        }

        return response()->view('public.event', $this->pages->for($model));
    }
}
