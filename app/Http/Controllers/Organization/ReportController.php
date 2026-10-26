<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\BasicReport;
use App\Domain\Fees\FinancialReport;
use App\Domain\Fees\IdempotencyConflict;
use App\Domain\Fees\RemittanceService;
use App\Http\Controllers\Controller;
use App\Models\RemittanceInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class ReportController extends Controller
{
    public function __construct(
        private readonly BasicReport $report,
        private readonly FinancialReport $financial,
        private readonly RemittanceService $remittance,
    ) {}

    public function index(): Response
    {
        return response()->view('organization.reports', $this->page());
    }

    public function csv(): Response
    {
        return response($this->financial->csv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="finance.csv"',
        ]);
    }

    public function openInvoice(): Response
    {
        try {
            $this->remittance->open();
        } catch (InvalidArgumentException $exception) {
            return response()->view('organization.reports', array_merge($this->page(), [
                'message' => $exception->getMessage(),
            ]), 422);
        }

        return response()->view('organization.reports', array_merge($this->page(), [
            'status' => 'Invoice opened. Fees stay owed until the invoice is marked paid.',
        ]));
    }

    public function markPaid(Request $request, RemittanceInvoice $invoice): Response
    {
        $validated = $this->validateOr422($request, [
            'payment_reference' => ['required', 'string', 'max:64'],
        ], 'organization.reports', $this->page());

        try {
            $this->remittance->markPaid($invoice, (string) $validated['payment_reference']);
        } catch (InvalidArgumentException|IdempotencyConflict $exception) {
            return response()->view('organization.reports', array_merge($this->page(), [
                'message' => $exception->getMessage(),
            ]), 422);
        }

        return response()->view('organization.reports', array_merge($this->page(), [
            'status' => 'Invoice marked paid. The booking fee was posted as remitted.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return [
            'title' => 'Reports',
            'summary' => $this->report->summarize(),
            'events' => $this->report->events(),
            'ledger' => $this->financial->totals(),
            'invoices' => RemittanceInvoice::query()->latest()->limit(50)->get(),
        ];
    }
}
