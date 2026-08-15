<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Warehouse;
use App\Models\StockTransaction;
use App\Models\JournalEntry;
use App\Models\CreditNote;
use App\Services\InvoiceService;
use App\Services\ReturnService;
use App\Services\ReturnJournalHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReturnJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->customer = Customer::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'selling_price' => 100.00,
            'purchase_price' => 80.00,
        ]);

        // Bill through the real order flow: the credit-note journal reverses the
        // original sales journal, so that sales journal has to exist first.
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'order_date' => now(),
            'total_amount' => 200.00,
            'fulfillment_location_id' => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $this->invoice = app(InvoiceService::class)->generateFromOrder($order);
    }

    /**
     * Approve a one-unit customer return against the seeded invoice and hand
     * back the credit note the approval generates.
     */
    private function approvedCreditNote(): CreditNote
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

        $approvedReturn = app(ReturnService::class)->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approvedReturn->id)->first();
        $this->assertNotNull($creditNote, 'Approving a customer return should generate a credit note');

        return $creditNote;
    }

    /** @test */
    public function it_creates_journal_entry_with_draft_status_for_customer_return()
    {
        $creditNote = $this->approvedCreditNote();

        $journalEntry = app(ReturnJournalHandler::class)->createCustomerReturnJournal($creditNote);

        // Draft on creation: the entry stays out of the ledger until it is
        // approved and then posted.
        $this->assertNotNull($journalEntry);
        $this->assertEquals('draft', $journalEntry->status);
        $this->assertStringContainsString('Customer Return', $journalEntry->description);
    }

    /** @test */
    public function it_creates_balanced_journal_entry_for_customer_return()
    {
        $creditNote = $this->approvedCreditNote();

        $journalEntry = app(ReturnJournalHandler::class)->createCustomerReturnJournal($creditNote);

        // The entry is linked to the credit note, not to the stock transaction.
        $this->assertEquals(CreditNote::class, $journalEntry->source_type);
        $this->assertEquals($creditNote->id, $journalEntry->source_id);

        $this->assertTrue($journalEntry->isBalanced());

        // Revenue and receivable reverse, and because the product carries a
        // purchase price the inventory and COGS legs reverse too.
        $this->assertCount(4, $journalEntry->lines);
        $this->assertEquals($journalEntry->totalDebit(), $journalEntry->totalCredit());
    }
}
