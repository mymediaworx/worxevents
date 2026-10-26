<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Support\BrandColor;
use App\Support\StoredImage;
use App\Tenancy\TenancyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SetupController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): Response
    {
        return response()->view('organization.setup', [
            'title' => 'Organization setup',
            'organization' => $this->organization($request),
        ]);
    }

    public function details(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $validated = $this->validateOr422($request, [
            'legal_name' => ['required', 'string', 'max:160'],
            'support_email' => ['required', 'email', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], 'organization.setup', ['organization' => $organization, 'title' => 'Organization setup']);

        $organization->fill([
            'legal_name' => $validated['legal_name'],
            'support_email' => $validated['support_email'],
            'description' => $validated['description'] ?? null,
            'setup_step' => max((int) $organization->setup_step, 1),
        ])->save();

        return redirect()->route('setup')->with('status', 'Organization details saved.');
    }

    public function branding(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $validated = $this->validateOr422($request, [
            'brand_accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
        ], 'organization.setup', ['organization' => $organization, 'title' => 'Organization setup']);

        $accent = BrandColor::sanitize(is_string($validated['brand_accent'] ?? null) ? $validated['brand_accent'] : null);
        $logo = $organization->logo_path;

        if ($request->file('logo') !== null) {
            StoredImage::delete($logo);
            $logo = StoredImage::store($request->file('logo'), 'organizations/'.$organization->id);
        }

        $organization->fill([
            'brand_accent' => $accent,
            'logo_path' => $logo,
            'setup_step' => max((int) $organization->setup_step, 2),
        ])->save();

        return redirect()->route('setup')->with('status', 'Branding saved.');
    }

    public function locale(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $validated = $this->validateOr422($request, [
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'in:USD'],
        ], 'organization.setup', ['organization' => $organization, 'title' => 'Organization setup']);

        $organization->fill([
            'timezone' => $validated['timezone'],
            'currency' => $validated['currency'],
            'setup_step' => max((int) $organization->setup_step, 3),
        ])->save();

        return redirect()->route('setup')->with('status', 'Currency and time zone saved.');
    }

    public function finish(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);

        if ($organization->legal_name === null || $organization->support_email === null || (int) $organization->setup_step < 3) {
            return redirect()->route('setup')->with('status', 'Save details, branding, and currency before finishing.');
        }

        $organization->fill([
            'setup_completed_at' => $organization->setup_completed_at ?? now(),
            'status' => $organization->status === OrganizationStatus::Onboarding
                ? OrganizationStatus::Active
                : $organization->status,
        ])->save();

        $this->audit->write('organization.setup_completed', $organization->id, $organization, [
            'currency' => $organization->currency,
            'timezone' => $organization->timezone,
        ]);

        return redirect()->route('dashboard')->with('status', 'Organization setup is complete.');
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            throw TenancyException::notFound();
        }

        return $organization;
    }
}
