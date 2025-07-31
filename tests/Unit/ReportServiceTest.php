<?php

namespace Tests\Unit;

use App\Services\ReportService;
use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $reportService;
    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->reportService = app(ReportService::class);
        $this->accountingService = app(AccountingService::class);
    }

    /** @test */
    public function generate_trial_balance_report_returns_correct_structure(): void
    {
        // Create a simple journal entry with posted status
        $this->accountingService->post([
            ['account_code' => '1000', 'debit' => 100, 'credit' => 0],
            ['account_code' => '2000', 'debit' => 0,  'credit' => 100],
        ], null, null, null, 'posted');

        $reportData = $this->reportService->generateTrialBalanceReport();

        $this->assertIsArray($reportData);
        $this->assertArrayHasKey('endDate', $reportData);
        $this->assertArrayHasKey('balances', $reportData);
        $this->assertArrayHasKey('totalDebit', $reportData);
        $this->assertArrayHasKey('totalCredit', $reportData);
        $this->assertArrayHasKey('isBalanced', $reportData);

        // Check that balances is a collection
        $this->assertIsObject($reportData['balances']);
        $this->assertTrue($reportData['balances']->count() > 0);

        // Check that totals are calculated correctly
        $this->assertGreaterThan(0, $reportData['totalDebit']);
        $this->assertGreaterThan(0, $reportData['totalCredit']);
        $this->assertTrue($reportData['isBalanced']);
    }

    /** @test */
    public function trial_balance_report_with_no_transactions_returns_empty_balances(): void
    {
        $reportData = $this->reportService->generateTrialBalanceReport();

        $this->assertIsArray($reportData);
        $this->assertArrayHasKey('balances', $reportData);
        $this->assertEquals(0, $reportData['totalDebit']);
        $this->assertEquals(0, $reportData['totalCredit']);
        $this->assertTrue($reportData['isBalanced']);
    }

    /** @test */
    public function generate_income_statement_report_returns_correct_structure(): void
    {
        // Create a simple journal entry with posted status for revenue
        $this->accountingService->post([
            ['account_code' => '4000', 'debit' => 0, 'credit' => 100], // Revenue
            ['account_code' => '1100', 'debit' => 100, 'credit' => 0], // Accounts Receivable
        ], null, null, null, 'posted');

        // Create a simple journal entry with posted status for expense
        $this->accountingService->post([
            ['account_code' => '5000', 'debit' => 50, 'credit' => 0], // Expense
            ['account_code' => '1000', 'debit' => 0, 'credit' => 50], // Cash
        ], null, null, null, 'posted');

        $reportData = $this->reportService->generateIncomeStatementReport();

        $this->assertIsArray($reportData);
        $this->assertArrayHasKey('startDate', $reportData);
        $this->assertArrayHasKey('endDate', $reportData);
        $this->assertArrayHasKey('revenues', $reportData);
        $this->assertArrayHasKey('expenses', $reportData);
        $this->assertArrayHasKey('totalRevenue', $reportData);
        $this->assertArrayHasKey('totalExpense', $reportData);
        $this->assertArrayHasKey('netIncome', $reportData);

        // Check that revenues and expenses are collections
        $this->assertIsObject($reportData['revenues']);
        $this->assertIsObject($reportData['expenses']);

        // Check that totals are calculated correctly
        // For income statement display, both revenue and expenses should be positive
        $this->assertGreaterThanOrEqual(0, $reportData['totalRevenue']); // Revenue should be positive for display
        $this->assertGreaterThanOrEqual(0, $reportData['totalExpense']); // Expenses should be positive for display
        $this->assertEquals($reportData['totalRevenue'] - $reportData['totalExpense'], $reportData['netIncome']);
    }

    /** @test */
    public function income_statement_report_with_no_transactions_returns_empty_data(): void
    {
        $reportData = $this->reportService->generateIncomeStatementReport();

        $this->assertIsArray($reportData);
        $this->assertArrayHasKey('revenues', $reportData);
        $this->assertArrayHasKey('expenses', $reportData);
        $this->assertEquals(0, $reportData['totalRevenue']);
        $this->assertEquals(0, $reportData['totalExpense']);
        $this->assertEquals(0, $reportData['netIncome']);
    }
} 