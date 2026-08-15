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
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReturnJournalReverseLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    /** @test */
    public function it_creates_customer_return_journal_with_built_in_reverse_logic()
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

        // Verify original sales journal entry was created
        $originalJournalEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->first();

        $this->assertNotNull($originalJournalEntry, 'Original sales journal entry should be created');

        // Create customer return
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

        // Approve the return (this should generate a credit note)
        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        // Check that a credit note was generated
        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();
        $this->assertNotNull($creditNote, 'Credit note should be generated');

        // Create journal entry with built-in reverse logic
        $returnJournalHandler = app(ReturnJournalHandler::class);
        $journalEntry = $returnJournalHandler->createCustomerReturnJournal($creditNote);

        // Verify the journal entry was created with draft status
        $this->assertEquals('draft', $journalEntry->status);
        $this->assertTrue($journalEntry->isBalanced());

        // Verify the reverse logic entries
        $lines = $journalEntry->lines()->with('account')->get();

        // Check Sales Returns & Allowances (5200) - Debit
        $salesReturnsLine = $lines->where('account.code', '5200')->first();
        $this->assertNotNull($salesReturnsLine, 'Sales Returns & Allowances line should exist');
        $this->assertEquals(50.00, $salesReturnsLine->debit); // 1 item * $50
        $this->assertEquals(0.00, $salesReturnsLine->credit);

        // Check Accounts Receivable (1100) - Credit
        $arLine = $lines->where('account.code', '1100')->first();
        $this->assertNotNull($arLine, 'Accounts Receivable line should exist');
        $this->assertEquals(0.00, $arLine->debit);
        $this->assertEquals(50.00, $arLine->credit); // 1 item * $50

        // Check Inventory (1200) - Debit (put inventory back)
        $inventoryLine = $lines->where('account.code', '1200')->first();
        $this->assertNotNull($inventoryLine, 'Inventory line should exist');
        $this->assertEquals(20.00, $inventoryLine->debit); // 1 item * $20 purchase price
        $this->assertEquals(0.00, $inventoryLine->credit);

        // Check Cost of Goods Sold (5000) - Credit (reverse COGS)
        $cogsLine = $lines->where('account.code', '5000')->first();
        $this->assertNotNull($cogsLine, 'Cost of Goods Sold line should exist');
        $this->assertEquals(0.00, $cogsLine->debit);
        $this->assertEquals(20.00, $cogsLine->credit); // 1 item * $20 purchase price

        // Verify the reverse logic explanation
        $explanation = $returnJournalHandler->getReverseLogicExplanation($journalEntry);
        $this->assertEquals('customer_return', $explanation['type']);
        $this->assertTrue($explanation['reverse_logic_applied']);
        $this->assertEquals($invoice->invoice_number, $explanation['original_transaction']['number']);
        $this->assertEquals(50.00, $explanation['original_transaction']['amount']);
    }

    /** @test */
    public function it_posts_customer_return_journal_with_reverse_logic()
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

        // Verify it's in draft status
        $this->assertEquals('draft', $journalEntry->status);

        // Approve before posting: posting is only open to approved entries.
        $returnJournalHandler->approveCustomerReturnJournal($creditNote);

        // Post the journal entry
        $returnJournalHandler->postCustomerReturnJournal($creditNote);

        // Refresh the journal entry
        $journalEntry->refresh();

        // Verify it's now posted
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertNotNull($journalEntry->posted_at);
    }

    /** @test */
    public function it_validates_reverse_logic_for_journal_entries()
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
        $this->assertTrue($isValid, 'Return journal entry should pass reverse logic validation');

        // Test with an unbalanced journal entry. This is built directly rather
        // than through AccountingService::post(), which refuses to create an
        // unbalanced entry at all - so formatted_id has to be supplied here.
        $unbalancedEntry = JournalEntry::create([
            'formatted_id' => 'JE-TEST-UNBALANCED',
            'entry_date' => now(),
            'description' => 'Test unbalanced entry',
            'status' => 'draft',
            'source_type' => CreditNote::class,
            'source_id' => $creditNote->id,
        ]);

        $unbalancedEntry->lines()->create([
            'account_id' => Account::where('code', '1100')->first()->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        $isValid = $returnJournalHandler->validateReverseLogic($unbalancedEntry);
        $this->assertFalse($isValid, 'Unbalanced journal entry should fail validation');
    }
} 