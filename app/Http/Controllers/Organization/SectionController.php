<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SectionController extends Controller
{
    public function events(Request $request): Response
    {
        return $this->page($request, 'Events', 'Event setup, ticket types, and capacity arrive in Phase 2. This page already checks the events.view permission.');
    }

    public function boxOffice(Request $request): Response
    {
        return $this->page($request, 'Box office', 'In-person sales arrive in a later phase. This page already checks the tickets.sell permission.');
    }

    public function checkIn(Request $request): Response
    {
        return $this->page($request, 'Check-in', 'Door scanning arrives in a later phase. This page already checks the checkin.scan permission.');
    }

    public function tickets(Request $request): Response
    {
        return $this->page($request, 'Your tickets', 'Buyer order history arrives with checkout. This page already checks the orders.view_own permission.');
    }

    private function page(Request $request, string $title, string $body): Response
    {
        $organization = $request->attributes->get('organization');

        return response()->view('organization.section', [
            'organization' => $organization instanceof Organization ? $organization : null,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
