<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Warehouse;
use App\Models\StockTransaction;
use App\Models\JournalEntry;
use App\Models\CreditNote;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReturnJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->customer = Customer::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'selling_price' => 100.00,
            'purchase_price' => 80.00,
        ]);
        
        $this->invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-001',
            'total' => 200.00,
        ]);
        
        $this->invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total' => 200.00,
        ]);
    }

    /** @test */
    public function it_creates_journal_entry_with_draft_status_for_customer_return()
    {
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        // Check that a credit note was generated
        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();
        $this->assertNotNull($creditNote);

        // Create journal entry with draft status
        $journalEntry = $creditNote->createJournalEntry();

        // Check that a journal entry was created with draft status
        $this->assertNotNull($journalEntry);
        $this->assertEquals('draft', $journalEntry->status);
        $this->assertStringContains('Customer Return', $journalEntry->description);
    }

    /** @test */
    public function it_creates_balanced_journal_entry_for_customer_return()
    {
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $returnService = app(ReturnService::class);
        $approvedReturn = $returnService->approveReturn($return);

        // Check that a journal entry was created
        $journalEntry = JournalEntry::where('source_type', StockTransaction::class)
            ->where('source_id', $approvedReturn->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertTrue($journalEntry->isBalanced());
        
        // Check that it has the correct lines
        $this->assertCount(2, $journalEntry->lines);
        
        // Check that total debit equals total credit
        $this->assertEquals($journalEntry->totalDebit(), $journalEntry->totalCredit());
    }
} 