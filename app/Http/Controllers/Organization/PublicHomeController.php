<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
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

        return response()->view('organization.public', [
            'organization' => $organization,
        ]);
    }
}
