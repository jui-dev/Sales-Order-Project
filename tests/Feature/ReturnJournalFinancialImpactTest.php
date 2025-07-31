<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\StockTransaction;
use App\Models\CreditNote;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Services\InvoiceService;
use App\Services\ReturnService;
use App\Services\ReturnJournalHandler;
use App\Services\AccountingService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReturnJournalFinancialImpactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    /** @test */
    public function it_creates_customer_return_journal_with_draft_status_no_financial_impact()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price' => 50,
        ]);

        // Create order and invoice (this will create the original sales journal entry)
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 100, // 2 * 50
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateFromOrder($order);

        // Create and approve customer return
        $return = StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => 1, // warehouse
            'location_type' => \App\Models\Warehouse::class,
            'quantity' => 1, // return 1 item
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        // Check that a credit note was generated
        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();
        $this->assertNotNull($creditNote, 'Credit note should be generated');

        // Get initial financial statement values
        $accountingService = app(AccountingService::class);
        $reportService = app(ReportService::class);
        
        $initialTrialBalance = $accountingService->trialBalance();
        $initialIncomeStatement = $reportService->generateIncomeStatementReport();
        $initialBalanceSheet = $reportService->generateBalanceSheetReport();

        // Create journal entry with built-in reverse logic (draft status)
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);

        // Verify the journal entry was created with draft status
        $this->assertEquals('draft', $journalEntry->status);
        $this->assertTrue($journalEntry->isBalanced());

        // Verify financial statements are NOT affected (draft status)
        $draftTrialBalance = $accountingService->trialBalance();
        $draftIncomeStatement = $reportService->generateIncomeStatementReport();
        $draftBalanceSheet = $reportService->generateBalanceSheetReport();

        // Trial balance should be unchanged
        $this->assertEquals(
            $initialTrialBalance->count(),
            $draftTrialBalance->count(),
            'Trial balance should not be affected by draft journal entry'
        );

        // Income statement should be unchanged
        $this->assertEquals(
            $initialIncomeStatement['totalRevenue'],
            $draftIncomeStatement['totalRevenue'],
            'Income statement revenue should not be affected by draft journal entry'
        );

        // Balance sheet should be unchanged
        $this->assertEquals(
            $initialBalanceSheet['totalAssets'],
            $draftBalanceSheet['totalAssets'],
            'Balance sheet should not be affected by draft journal entry'
        );
    }

    /** @test */
    public function it_posts_customer_return_journal_and_affects_financial_statements()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price' => 50,
        ]);

        // Create order and invoice
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 100,
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateFromOrder($order);

        // Create and approve return
        $return = StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => 1,
            'location_type' => \App\Models\Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();

        // Create journal entry
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);

        // Get financial statement values before posting
        $accountingService = app(AccountingService::class);
        $reportService = app(ReportService::class);
        
        $beforeTrialBalance = $accountingService->trialBalance();
        $beforeIncomeStatement = $reportService->generateIncomeStatementReport();
        $beforeBalanceSheet = $reportService->generateBalanceSheetReport();

        // Post the journal entry
        $returnJournalHandler->postCustomerReturnJournal($creditNote);

        // Refresh the journal entry
        $journalEntry->refresh();

        // Verify it's now posted
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertNotNull($journalEntry->posted_at);

        // Get financial statement values after posting
        $afterTrialBalance = $accountingService->trialBalance();
        $afterIncomeStatement = $reportService->generateIncomeStatementReport();
        $afterBalanceSheet = $reportService->generateBalanceSheetReport();

        // Verify financial statements are NOW affected
        $this->assertNotEquals(
            $beforeTrialBalance->count(),
            $afterTrialBalance->count(),
            'Trial balance should be affected by posted journal entry'
        );

        // Verify specific account impacts
        $salesReturnsAccount = $afterTrialBalance->get('5200'); // Sales Returns & Allowances
        $this->assertNotNull($salesReturnsAccount, 'Sales Returns & Allowances account should be in trial balance');
        $this->assertEquals(50.00, $salesReturnsAccount['debit'], 'Sales Returns & Allowances should be debited');

        $accountsReceivableAccount = $afterTrialBalance->get('1100'); // Accounts Receivable
        $this->assertNotNull($accountsReceivableAccount, 'Accounts Receivable account should be in trial balance');
        $this->assertEquals(50.00, $accountsReceivableAccount['credit'], 'Accounts Receivable should be credited');

        // Verify income statement impact
        $this->assertNotEquals(
            $beforeIncomeStatement['totalRevenue'],
            $afterIncomeStatement['totalRevenue'],
            'Income statement should be affected by posted journal entry'
        );

        // Verify balance sheet impact
        $this->assertNotEquals(
            $beforeBalanceSheet['totalAssets'],
            $afterBalanceSheet['totalAssets'],
            'Balance sheet should be affected by posted journal entry'
        );
    }

    /** @test */
    public function it_calculates_financial_statement_impact_correctly()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price' => 50,
        ]);

        // Create order and invoice
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 100,
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateFromOrder($order);

        // Create and approve return
        $return = StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => 1,
            'location_type' => \App\Models\Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();

        // Create and post journal entry
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);
        $returnJournalHandler->postCustomerReturnJournal($creditNote);

        // Get financial impact summary
        $financialImpact = $returnJournalHandler->getFinancialImpactSummary($journalEntry);

        // Verify financial impact summary
        $this->assertEquals('posted', $financialImpact['status']);
        $this->assertEquals('customer_return', $financialImpact['return_type']);
        $this->assertGreaterThan(0, $financialImpact['total_impact']['trial_balance_entries']);
        $this->assertGreaterThan(0, $financialImpact['total_impact']['income_statement_entries']);
        $this->assertGreaterThan(0, $financialImpact['total_impact']['balance_sheet_entries']);

        // Verify key effects
        $this->assertContains('Revenue reduced via Sales Returns & Allowances', $financialImpact['key_effects']);
        $this->assertContains('Accounts Receivable reduced', $financialImpact['key_effects']);

        // Get detailed reverse logic explanation
        $explanation = $returnJournalHandler->getReverseLogicExplanation($journalEntry);

        // Verify reverse logic explanation
        $this->assertTrue($explanation['reverse_logic_applied']);
        $this->assertEquals('customer_return', $explanation['type']);
        $this->assertNotNull($explanation['financial_impact']);
        $this->assertArrayHasKey('trial_balance', $explanation['financial_impact']);
        $this->assertArrayHasKey('income_statement', $explanation['financial_impact']);
        $this->assertArrayHasKey('balance_sheet', $explanation['financial_impact']);
        $this->assertArrayHasKey('cash_flow', $explanation['financial_impact']);
    }

    /** @test */
    public function it_validates_reverse_logic_for_customer_return_journal()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price' => 50,
        ]);

        // Create order and invoice
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 100,
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateFromOrder($order);

        // Create and approve return
        $return = StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => 1,
            'location_type' => \App\Models\Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();

        // Create journal entry
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);

        // Validate the reverse logic
        $isValid = $returnJournalHandler->validateReverseLogic($journalEntry);
        $this->assertTrue($isValid, 'Customer return journal entry should pass reverse logic validation');

        // Test with an invalid journal entry (wrong account codes)
        $invalidEntry = JournalEntry::create([
            'entry_date' => now(),
            'description' => 'Invalid customer return entry',
            'status' => 'draft',
            'source_type' => CreditNote::class,
            'source_id' => $creditNote->id,
        ]);

        $invalidEntry->lines()->create([
            'account_id' => Account::where('code', '1000')->first()->id, // Cash (wrong account)
            'debit' => 50,
            'credit' => 0,
        ]);

        $invalidEntry->lines()->create([
            'account_id' => Account::where('code', '2000')->first()->id, // Accounts Payable (wrong account)
            'debit' => 0,
            'credit' => 50,
        ]);

        $isValid = $returnJournalHandler->validateReverseLogic($invalidEntry);
        $this->assertFalse($isValid, 'Invalid customer return journal entry should fail validation');
    }

    /** @test */
    public function it_ensures_draft_journals_do_not_affect_financial_statements()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price' => 50,
        ]);

        // Create order and invoice
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 100,
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateFromOrder($order);

        // Create and approve return
        $return = StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => 1,
            'location_type' => \App\Models\Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();

        // Get initial financial statement values
        $accountingService = app(AccountingService::class);
        $reportService = app(ReportService::class);
        
        $initialTrialBalance = $accountingService->trialBalance();
        $initialIncomeStatement = $reportService->generateIncomeStatementReport();
        $initialBalanceSheet = $reportService->generateBalanceSheetReport();
        $initialCashFlow = $reportService->generateCashFlowReport();

        // Create journal entry (draft status)
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);

        // Verify journal entry is in draft status
        $this->assertEquals('draft', $journalEntry->status);

        // Get financial statement values after creating draft journal entry
        $draftTrialBalance = $accountingService->trialBalance();
        $draftIncomeStatement = $reportService->generateIncomeStatementReport();
        $draftBalanceSheet = $reportService->generateBalanceSheetReport();
        $draftCashFlow = $reportService->generateCashFlowReport();

        // Verify NO financial impact from draft journal entry
        $this->assertEquals(
            $initialTrialBalance->count(),
            $draftTrialBalance->count(),
            'Trial balance should not be affected by draft journal entry'
        );

        $this->assertEquals(
            $initialIncomeStatement['totalRevenue'],
            $draftIncomeStatement['totalRevenue'],
            'Income statement should not be affected by draft journal entry'
        );

        $this->assertEquals(
            $initialBalanceSheet['totalAssets'],
            $draftBalanceSheet['totalAssets'],
            'Balance sheet should not be affected by draft journal entry'
        );

        $this->assertEquals(
            $initialCashFlow['netCashFlow'],
            $draftCashFlow['netCashFlow'],
            'Cash flow should not be affected by draft journal entry'
        );

        // Verify financial impact summary shows draft status
        $financialImpact = $returnJournalHandler->getFinancialImpactSummary($journalEntry);
        $this->assertEquals('draft', $financialImpact['status']);
        $this->assertEquals('Journal entry is in draft status - no financial impact yet', $financialImpact['message']);
    }
} 