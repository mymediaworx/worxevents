<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Venue;
use App\Support\CatalogSlug;
use App\Tenancy\TenancyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class VenueController extends Controller
{
    public function index(): Response
    {
        return response()->view('organization.venues.index', [
            'title' => 'Venues',
            'venues' => Venue::query()->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function create(): Response
    {
        return response()->view('organization.venues.form', [
            'title' => 'New venue',
            'venue' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $validated = $this->validated($request, null);

        Venue::query()->create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => $this->slug($validated['name']),
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'region' => $validated['region'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
        ]);

        return redirect()->route('venues')->with('status', 'Venue saved.');
    }

    public function edit(Venue $venue): Response
    {
        return response()->view('organization.venues.form', [
            'title' => 'Edit venue',
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $validated = $this->validated($request, $venue);
        $venue->fill([
            'name' => $validated['name'],
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'region' => $validated['region'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
        ])->save();

        return redirect()->route('venues')->with('status', 'Venue updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Venue $venue): array
    {
        return $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:160'],
            'address_line' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ], 'organization.venues.form', [
            'title' => $venue === null ? 'New venue' : 'Edit venue',
            'venue' => $venue,
        ]);
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            throw TenancyException::notFound();
        }

        return $organization;
    }

    private function slug(string $name): string
    {
        return CatalogSlug::from($name, fn (string $slug): bool => Venue::query()->where('slug', $slug)->exists());
    }
}
