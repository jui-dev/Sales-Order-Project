<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * Show Daily Profit Report page with calculated data.
     */
    public function dailyProfit(Request $request): View
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $filters = [
            'start_date' => $request->start_date ?: now()->startOfMonth()->toDateString(),
            'end_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateDailyProfitReport($filters);

        return view('reports.daily-profit', $reportData);
    }

    /**
     * Show Trial Balance Report
     */
    public function trialBalance(Request $request)
    {
        $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'export' => ['nullable', 'string', 'in:pdf,csv'],
        ]);

        $filters = [
            'as_of_date' => $request->as_of_date ?: $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateTrialBalanceReport($filters);

        // Handle export requests
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.trial-balance-pdf', $reportData);
            return $pdf->download('trial-balance-report.pdf');
        }

        // A trial balance runs from the start of the books, so the backlog that
        // matters is everything unposted up to the as-of date.
        $reportData['basis'] = $this->reportService->statementBasis(null, $filters['as_of_date']);

        return view('reports.trial-balance', $reportData);
    }

    /**
     * Show Income Statement Report
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'export' => ['nullable', 'string', 'in:pdf,csv'],
        ]);

        $filters = [
            'start_date' => $request->start_date ?: now()->startOfYear()->toDateString(),
            'end_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateIncomeStatementReport($filters);

        // Handle export requests
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.income-statement-pdf', $reportData);
            return $pdf->download('income-statement-report.pdf');
        }

        $reportData['basis'] = $this->reportService->statementBasis(
            $filters['start_date'],
            $filters['end_date']
        );

        return view('reports.income-statement', $reportData);
    }

    /**
     * Show Balance Sheet Report
     */
    public function balanceSheet(Request $request)
    {
        $request->validate([
            'end_date' => ['nullable', 'date'],
            'export' => ['nullable', 'string', 'in:pdf'],
        ]);

        $filters = [
            'as_of_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateBalanceSheetReport($filters);

        // The page offered a PDF button all along; the method existed but no
        // route ever reached it, so the button did nothing.
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.balance-sheet-pdf', $reportData);
            return $pdf->download('balance-sheet-report.pdf');
        }

        $reportData['basis'] = $this->reportService->statementBasis(null, $filters['as_of_date']);

        return view('reports.balance-sheet', $reportData);
    }

    /**
     * Show Cash Flow Statement Report
     */
    public function cashFlowStatement(Request $request)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'export' => ['nullable', 'string', 'in:pdf'],
        ]);

        $filters = [
            'start_date' => $request->start_date ?: now()->startOfYear()->toDateString(),
            'end_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateCashFlowStatementReport($filters);

        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.cash-flow-pdf', $reportData);
            return $pdf->download('cash-flow-report.pdf');
        }

        $reportData['basis'] = $this->reportService->statementBasis(
            $filters['start_date'],
            $filters['end_date']
        );

        return view('reports.cash-flow', $reportData);
    }

    /**
     * Download Balance Sheet Report as PDF
     */
    public function balanceSheetPdf(Request $request)
    {
        $request->validate([
            'end_date' => ['nullable', 'date'],
        ]);

        $filters = [
            'as_of_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateBalanceSheetReport($filters);

        $pdf = Pdf::loadView('reports.balance-sheet-pdf', $reportData);
        return $pdf->download('balance-sheet-report.pdf');
    }

    /**
     * Download Cash Flow Statement Report as PDF
     */
    public function cashFlowStatementPdf(Request $request)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $filters = [
            'start_date' => $request->start_date ?: now()->startOfYear()->toDateString(),
            'end_date' => $request->end_date ?: now()->toDateString(),
        ];

        $reportData = $this->reportService->generateCashFlowStatementReport($filters);

        $pdf = Pdf::loadView('reports.cash-flow-pdf', $reportData);
        return $pdf->download('cash-flow-statement-report.pdf');
    }
} 