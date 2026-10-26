<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Catalog\PublishedEvents;
use App\Enums\EventStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Venue;
use App\Support\BrandColor;
use App\Support\PageSize;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

final class BrowseController extends Controller
{
    public function __construct(
        private readonly PublishedEvents $published,
        private readonly OrganizationManager $organizations,
    ) {}

    public function index(Request $request): Response
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, string $slug): Response
    {
        return $this->listing($request, $slug);
    }

    public function organizer(string $slug): Response
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->whereIn('status', [OrganizationStatus::Onboarding, OrganizationStatus::Active])
            ->first();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        return $this->organizerPage($organization);
    }

    public function currentOrganizer(Request $request): Response
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            abort(404);
        }

        return $this->organizerPage($organization);
    }

    public function venue(string $slug): Response
    {
        $venue = Venue::query()->with('organization')->where('slug', $slug)->first();

        if (! $venue instanceof Venue) {
            abort(404);
        }

        $events = Event::query()
            ->where('venue_id', $venue->id)
            ->where('status', EventStatus::Published)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        return response()->view('public.venue', [
            'title' => $venue->name,
            'description' => $venue->addressLine() !== '' ? $venue->addressLine() : $venue->name,
            'venue' => $venue,
            'events' => $events,
        ]);
    }

    public function platformVenue(string $organization, string $venue): Response
    {
        $org = $this->publicOrganization($organization);
        $model = Venue::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('organization_id', $org->id)
            ->where('slug', $venue)
            ->first();

        if (! $model instanceof Venue) {
            abort(404);
        }

        $events = $this->published->query()
            ->where('venue_id', $model->id)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        return response()->view('public.venue', [
            'title' => $model->name,
            'description' => $model->addressLine() !== '' ? $model->addressLine() : $model->name,
            'venue' => $model,
            'events' => $events,
        ]);
    }

    private function listing(Request $request, ?string $category): Response
    {
        $query = $this->published->query()->orderBy('starts_at');
        $organizationId = $this->organizations->id();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($category !== null) {
            $query->whereHas('category', function (Builder $inner) use ($category): void {
                $inner->withoutGlobalScope(OrganizationScope::class)->where('slug', $category);
            });
        }

        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhereHas('organization', fn (Builder $org) => $org->where('name', 'like', $like));
            });
        }

        $tag = trim((string) $request->query('tag', ''));

        if ($tag !== '') {
            $query->whereHas('tags', function (Builder $inner) use ($tag): void {
                $inner->withoutGlobalScope(OrganizationScope::class)->where('slug', $tag);
            });
        }

        $city = trim((string) $request->query('city', ''));

        if ($city !== '') {
            $like = '%'.addcslashes($city, '\\%_').'%';
            $query->whereHas('venue', function (Builder $inner) use ($like): void {
                $inner->withoutGlobalScope(OrganizationScope::class)->where('city', 'like', $like);
            });
        }

        $from = $this->day($request->query('from'), true);
        $to = $this->day($request->query('to'), false);

        if ($from !== null) {
            $query->where('starts_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('starts_at', '<=', $to);
        }

        $events = $query->paginate(PageSize::clamp((int) $request->query('per_page', 20)))->withQueryString();

        return response()->view('public.browse', [
            'title' => $category === null ? 'Browse events' : 'Category: '.$category,
            'description' => 'Search published events on WorxEvents.',
            'events' => $events,
            'category' => $category,
            'filters' => [
                'q' => $term,
                'city' => $city,
                'tag' => $tag,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
        ]);
    }

    private function organizerPage(Organization $organization): Response
    {
        $events = $this->published->query()
            ->where('organization_id', $organization->id)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        return response()->view('public.organizer', [
            'title' => $organization->name,
            'description' => $organization->description ?: $organization->name,
            'organization' => $organization,
            'events' => $events,
            'accent' => BrandColor::sanitize($organization->brand_accent),
        ]);
    }

    private function publicOrganization(string $slug): Organization
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->whereIn('status', [OrganizationStatus::Onboarding, OrganizationStatus::Active])
            ->first();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        return $organization;
    }

    private function day(mixed $value, bool $start): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $day = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $start ? $day->startOfDay() : $day->endOfDay();
    }
}
