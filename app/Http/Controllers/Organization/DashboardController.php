<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Tenancy\TenancyException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('membership');

        if (! $organization instanceof Organization || ! $membership instanceof OrganizationMember) {
            throw TenancyException::notFound();
        }

        $membership->loadMissing('role');

        return response()->view('organization.dashboard', [
            'organization' => $organization,
            'membership' => $membership,
        ]);
    }
}
