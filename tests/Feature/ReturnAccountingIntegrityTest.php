<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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
use App\Services\ReturnJournalHandler;
use App\Services\ReturnService;
use App\Support\InventoryLocationAccount;
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
        $this->assertEquals('draft', $journal->status);
        $this->assertTrue($journal->isBalanced());

        $retailerCode  = InventoryLocationAccount::codeFor(Retailer::class, $this->retailer->id);
        $warehouseCode = InventoryLocationAccount::codeFor(Warehouse::class, $this->warehouse->id);

        $byCode = $journal->lines->mapWithKeys(
            fn ($line) => [$line->account->code => ['debit' => (float) $line->debit, 'credit' => (float) $line->credit]]
        );

        // 5 units at the 40.00 the product is carried for.
        $this->assertEquals(200.00, $byCode[$retailerCode]['credit']);
        $this->assertEquals(200.00, $byCode[$warehouseCode]['debit']);
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

        $retailerCode = InventoryLocationAccount::codeFor(Retailer::class, $this->retailer->id);
        $retailerAccount = Account::where('code', $retailerCode)->first();
        $this->assertNotNull($retailerAccount);

        $lines = $retailerAccount->journalEntryLines;
        $net = $lines->sum(fn ($line) => (float) $line->debit - (float) $line->credit);

        // Out and back at the same price nets to nothing. Before the return
        // posted an entry, the outbound leg sat here on its own forever.
        $this->assertEquals(0.0, round($net, 2));
    }
}
