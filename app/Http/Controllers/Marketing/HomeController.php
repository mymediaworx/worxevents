<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\PublishedEvents;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class HomeController extends Controller
{
    public function __construct(private readonly PublishedEvents $events) {}

    public function __invoke(): Response
    {
        return response()->view('marketing.home', [
            'title' => 'WorxEvents',
            'description' => 'Ticketing and registration for organizers who keep the customer relationship.',
            'events' => $this->events->upcoming()->limit(8)->get(),
        ]);
    }
}
