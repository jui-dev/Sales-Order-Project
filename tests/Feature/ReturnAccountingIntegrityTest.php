<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Retailer;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\Supply;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\CreditNoteService;
use App\Services\DebitNoteService;
use App\Services\ReturnJournalHandler;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the three ways a return used to get its accounting wrong without
 * saying so: a debit note valued from the wrong column, a note that failed to
 * generate while the return stayed approved, and a retailer return that moved
 * stock but left its value behind at the retailer.
 */
class ReturnAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected Warehouse $warehouse;
    protected Retailer $retailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->actingAs(User::factory()->create());

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->retailer  = Retailer::factory()->create(['name' => 'Main Retailer']);

        // The price the product carries *today*, deliberately different from
        // what the vendor billed - recording a supply refreshes this, so the
        // two drift apart in normal use.
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'purchase_price' => 40.00,
        ]);
    }

    /**
     * A posted bill for $unitCost per unit, with the GRN/supply chain the
     * vendor return needs to resolve its warehouse.
     */
    private function postedBill(float $unitCost, int $quantity = 100): SupplierBill
    {
        $vendor = Vendor::factory()->create();
        $supply = Supply::factory()->create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'posted']);

        // Left as draft so it can be posted through the service below, which is
        // what gives it the purchase journal the vendor return has to reverse.
        $bill = SupplierBill::factory()->create([
            'vendor_id' => $vendor->id,
            'grn_id' => $grn->id,
            'status' => 'draft',
            'total_amount' => $unitCost * $quantity,
        ]);

        SupplierBillItem::create([
            'supplier_bill_id' => $bill->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $unitCost * $quantity,
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => $quantity,
        ]);

        return app(\App\Services\SupplierBillService::class)->postSupplierBill($bill);
    }

    /**
     * Net movement per account code across *posted* entries only. A draft or
     * approved entry has not reached the books, so it must not show up here.
     */
    private function postedBalances(): array
    {
        return app(AccountingService::class)->trialBalance()
            ->map(fn ($row) => round($row['debit'] - $row['credit'], 2))
            ->all();
    }

    /**
     * An approved vendor return and the debit note it raised, against a bill
     * that carries a real purchase journal.
     *
     * @return array{0: SupplierBill, 1: DebitNote}
     */
    private function vendorReturnNote(float $unitCost = 25.00, int $quantity = 4): array
    {
        $bill = $this->postedBill($unitCost);

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $bill->vendor_id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);
        app(ReturnService::class)->approveReturn($return);

        $note = DebitNote::where('return_transaction_id', $return->id)->firstOrFail();

        return [$bill->fresh(), $note];
    }

    /**
     * An approved customer return and the credit note it raised. The invoice
     * gets its sales journal from InvoiceObserver when it is created.
     */
    private function customerReturnNote(int $quantity = 4): CreditNote
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 125.00,
        ]);

        $return = app(ReturnService::class)->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'return_reason' => 'defective_product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);
        app(ReturnService::class)->approveReturn($return);

        return CreditNote::where('return_transaction_id', $return->id)->firstOrFail();
    }

    private function retailerReturn(int $quantity = 5, int $stockAtRetailer = 20): StockTransaction
    {
        $transfer = StockTransfer::factory()->create([
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => Retailer::class,
            'to_location_id' => $this->retailer->id,
            'status' => 'completed',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => $stockAtRetailer,
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->retailer->id,
            'location_type' => Retailer::class,
            'quantity' => $stockAtRetailer,
        ]);

        return app(ReturnService::class)->createRetailerReturn([
            'stock_transfer_id' => $transfer->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'return_reason' => 'excess_inventory',
            'return_date' => now(),
        ]);
    }

    /** @test */
    public function debit_note_is_valued_at_the_billed_cost_not_todays_product_price()
    {
        // Billed at 25, but the product now costs 40.
        $bill = $this->postedBill(25.00);

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $bill->vendor_id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);

        app(ReturnService::class)->approveReturn($return);

        $debitNote = DebitNote::where('return_transaction_id', $return->id)->first();
        $this->assertNotNull($debitNote, 'Approving a vendor return should raise a debit note');

        // 4 x 25.00 billed, not 4 x 40.00 current.
        $this->assertEquals(100.00, (float) $debitNote->total_amount);
        $this->assertEquals('supplier_bill', $debitNote->metadata['price_source']);
        $this->assertEquals(25.00, (float) $debitNote->metadata['original_unit_price']);
    }

    /** @test */
    public function vendor_return_journal_books_the_price_difference_to_purchase_returns()
    {
        $bill = $this->postedBill(25.00);

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $bill->vendor_id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);
        app(ReturnService::class)->approveReturn($return);

        $debitNote = DebitNote::where('return_transaction_id', $return->id)->first();
        $journal = app(ReturnJournalHandler::class)->createVendorReturnJournal($debitNote);

        $this->assertTrue($journal->isBalanced());

        $byCode = $journal->lines->mapWithKeys(
            fn ($line) => [$line->account->code => ['debit' => (float) $line->debit, 'credit' => (float) $line->credit]]
        );

        // Credited to the vendor at the billed price: 4 x 25 = 100.
        $this->assertEquals(100.00, $byCode['2000']['debit']);
        // Stock leaves at what it is carried for: 4 x 40 = 160.
        $this->assertEquals(160.00, $byCode['1200']['credit']);
        // The 60 difference is a price adjustment, not a stock movement. This
        // line never appeared while the note was valued off the same figure.
        $this->assertEquals(60.00, $byCode['5100']['debit']);
    }

    /** @test */
    public function a_return_whose_note_cannot_be_raised_moves_no_stock_and_stays_pending()
    {
        // A bill with no line for this product: the debit note cannot be built.
        $vendor = Vendor::factory()->create();
        $supply = Supply::factory()->create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'posted']);
        $bill = SupplierBill::factory()->create([
            'vendor_id' => $vendor->id,
            'grn_id' => $grn->id,
            'status' => 'posted',
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);

        try {
            app(ReturnService::class)->approveReturn($return);
            $this->fail('Approval should fail when the debit note cannot be raised');
        } catch (\Exception $e) {
            // Expected - the approval is refused rather than half-applied.
        }

        $return->refresh();

        // Nothing half-done: status, stock and note all roll back together.
        $this->assertEquals(StockTransaction::STATUS_PENDING, $return->status);
        $this->assertNull($return->stock_posted_at);
        $this->assertEquals(100, ProductStock::where('product_id', $this->product->id)
            ->where('location_type', Warehouse::class)
            ->value('quantity'));
        $this->assertDatabaseCount('debit_notes', 0);
    }

    /** @test */
    public function approving_a_retailer_return_moves_inventory_value_back_to_the_warehouse()
    {
        $return = $this->retailerReturn(quantity: 5);

        app(ReturnService::class)->approveReturn($return);

        $journal = JournalEntry::where('source_type', $return->getMorphClass())
            ->where('source_id', $return->getKey())
            ->first();

        $this->assertNotNull($journal, 'A retailer return should move its inventory value back');
        $this->assertEquals('posted', $journal->status);
        $this->assertTrue($journal->isBalanced());

        // Both sides are the one inventory account, told apart by the location
        // on the line. There is no 1200-RT account any more: an account per
        // warehouse never received purchase or sale value, so it could only
        // ever go negative and reconcile to nothing.
        $byLocation = $journal->lines->mapWithKeys(fn ($line) => [
            $line->location_type . ':' . $line->location_id => [
                'debit'  => (float) $line->debit,
                'credit' => (float) $line->credit,
            ],
        ]);

        $retailerKey  = Retailer::class . ':' . $this->retailer->id;
        $warehouseKey = Warehouse::class . ':' . $this->warehouse->id;

        // 5 units at the 40.00 the product is carried for.
        $this->assertEquals(200.00, $byLocation[$retailerKey]['credit']);
        $this->assertEquals(200.00, $byLocation[$warehouseKey]['debit']);
    }

    /** @test */
    public function a_transfer_out_and_return_round_trip_leaves_no_value_at_the_retailer()
    {
        // Send 20 units out to the retailer, which posts WH -> RT.
        $transfer = StockTransfer::factory()->create([
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => Retailer::class,
            'to_location_id' => $this->retailer->id,
            'status' => 'pending',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);
        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->retailer->id,
            'location_type' => Retailer::class,
            'quantity' => 20,
        ]);

        // Completing it fires StockTransferObserver.
        $transfer->update(['status' => 'completed']);

        // Now send all 20 back.
        $return = app(ReturnService::class)->createRetailerReturn([
            'stock_transfer_id' => $transfer->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'return_reason' => 'excess_inventory',
            'return_date' => now(),
        ]);
        app(ReturnService::class)->approveReturn($return);

        // What the retailer holds is a grouping of the one inventory account.
        $atRetailer = app(\App\Accounting\LedgerService::class)
            ->locationBalances(\App\Accounting\AccountRole::Inventory)
            ->get(Retailer::class . ':' . $this->retailer->id);

        // Out and back at the same price nets to nothing. Before the return
        // posted an entry, the outbound leg sat here on its own forever.
        $this->assertEquals(0.0, (float) ($atRetailer['balance']?->toDecimal() ?? 0));
    }

    /** @test */
    public function posting_a_debit_note_raises_an_entry_that_reverses_the_purchase_journal()
    {
        [$bill, $note] = $this->vendorReturnNote();

        $this->assertEquals('issued', $note->status);
        $this->assertNotNull($bill->purchase_journal_id, 'The bill must carry the journal the note reverses');

        $before = $this->postedBalances();

        app(DebitNoteService::class)->postDebitNote($note);
        $note->refresh();

        $this->assertEquals('posted', $note->status);

        $entry = $note->journalEntry;
        $this->assertNotNull($entry, 'Posting the note should raise its journal entry');

        // A return entry is the system's own record of a document somebody has
        // already approved. Approving the system's arithmetic a second time
        // adds no control - it only holds the books behind reality - so the
        // entry is posted when it is raised, like an invoice or a bill.
        $this->assertEquals('posted', $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertTrue($entry->isBalanced());

        // It is a reversal, and it names what it reverses.
        $this->assertTrue((bool) $entry->is_reverse);
        $this->assertEquals($bill->purchase_journal_id, $entry->reverses_journal_id);
        $this->assertEquals($note->id, $entry->linked_debit_note_id);

        $after = $this->postedBalances();

        // 4 x 25 credited by the vendor, so what we owe them falls.
        $this->assertEquals(100.00, round($after['2000'] - ($before['2000'] ?? 0), 2));
        // 4 x 40 leaves inventory at what it is carried for.
        $this->assertEquals(-160.00, round($after['1200'] - ($before['1200'] ?? 0), 2));
    }

    /** @test */
    public function a_posted_return_entry_cannot_be_edited_or_re_approved()
    {
        [, $note] = $this->vendorReturnNote();

        app(DebitNoteService::class)->postDebitNote($note);
        $entry = $note->fresh()->journalEntry;

        // The approval path belongs to entries a person typed. A posted entry
        // is a matter of record: correcting one means posting another against
        // it, never changing it in place.
        $this->expectException(\RuntimeException::class);
        $entry->approve();
    }

    /** @test */
    public function a_customer_return_reaches_the_books_when_the_note_is_posted()
    {
        $note = $this->customerReturnNote();

        $before = $this->postedBalances();

        app(CreditNoteService::class)->postCreditNote($note);
        $entry = $note->fresh()->journalEntry;

        $this->assertEquals('posted', $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertTrue($entry->isBalanced());

        $after = $this->postedBalances();

        // 4 x 125 refunded: receivables fall, and the same figure lands in
        // sales returns rather than being taken back out of revenue.
        $this->assertEquals(-500.00, round($after['1100'] - ($before['1100'] ?? 0), 2));
        $this->assertEquals(500.00, round($after['4200'] - ($before['4200'] ?? 0), 2));
        // 4 x 40 of stock comes back, and the COGS relieved on the sale with it.
        $this->assertEquals(160.00, round($after['1200'] - ($before['1200'] ?? 0), 2));
        $this->assertEquals(-160.00, round($after['5000'] - ($before['5000'] ?? 0), 2));
    }
}
