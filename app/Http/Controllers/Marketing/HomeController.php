<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('marketing.home');
    }
}
