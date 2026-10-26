<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SectionController extends Controller
{
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
        $orders = Order::query()
            ->where('buyer_user_id', $request->user()?->id)
            ->with(['lines', 'attendees'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->view('organization.tickets', [
            'title' => 'Your tickets',
            'orders' => $orders,
        ]);
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
