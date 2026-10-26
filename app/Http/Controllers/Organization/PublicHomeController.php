<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Support\BrandColor;
use App\Tenancy\TenancyException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicHomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            throw TenancyException::notFound();
        }

        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->with(['venue', 'category', 'ticketTypes'])
            ->orderBy('starts_at')
            ->limit(24)
            ->get();

        return response()->view('organization.public', [
            'title' => $organization->name,
            'description' => $organization->description ?: $organization->name,
            'organization' => $organization,
            'events' => $events,
            'accent' => BrandColor::sanitize($organization->brand_accent),
        ]);
    }
}
