<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RegistrationController extends Controller
{
    public function show(string $reference): Response
    {
        return response()->view('public.registration', [
            'title' => 'Find your registration',
            'robots' => 'noindex, nofollow',
            'reference' => $reference,
            'order' => null,
        ]);
    }

    public function confirm(Request $request, string $reference): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $order = Order::query()
            ->where('reference', $reference)
            ->where('guest_email', $email)
            ->with(['lines', 'attendees'])
            ->first();

        if (! $order instanceof Order) {
            abort(404);
        }

        return response()->view('public.registration', [
            'title' => 'Registration '.$order->reference,
            'robots' => 'noindex, nofollow',
            'reference' => $reference,
            'order' => $order,
        ]);
    }
}
