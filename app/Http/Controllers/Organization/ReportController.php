<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\BasicReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ReportController extends Controller
{
    public function __invoke(BasicReport $report): Response
    {
        return response()->view('organization.reports', [
            'title' => 'Reports',
            'summary' => $report->summarize(),
            'events' => $report->events(),
        ]);
    }
}
