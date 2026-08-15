<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Grn;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\SupplierBillService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingObserversTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function grn_posting_creates_balanced_journal_entry(): void
    {
        // ------------------------------------------------------------------
        // Seed chart of accounts (Inventory 1200, A/P 2000, etc.)
        // ------------------------------------------------------------------
        $this->seed(ChartOfAccountsSeeder::class);

        // Minimal supporting data
        $vendor    = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create(['purchase_price' => 10]);

        // Create a Supply with one item
        /** @var Supply $supply */
        $supply = Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 100,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id'=> $product->id,
            'quantity'  => 10,
            'unit_cost' => 10,
            'subtotal'  => 100,
        ]);

        // Create GRN (draft)
        /** @var Grn $grn */
        $grn = Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        // ------------------------------------------------------------------
        // Action: mark GRN as posted → observer should fire
        // ------------------------------------------------------------------
        $grn->update(['status' => 'posted']);

        // Receiving the goods raises the bill but deliberately stays off the
        // ledger - GrnObserver creates a draft supplier bill "instead of
        // posting journal entry", and nothing is owed until that bill is posted.
        $this->assertNull(
            JournalEntry::where('source_type', $grn->getMorphClass())
                ->where('source_id', $grn->id)
                ->first(),
            'Posting a GRN should not reach the ledger on its own'
        );

        $bill = $grn->fresh()->supplierBill;

        $this->assertNotNull($bill, 'Supplier bill not created for GRN posting');
        $this->assertSame('draft', $bill->status);
        $this->assertEquals(100.00, $bill->total_amount);

        // Posting the bill is what recognises the liability.
        app(SupplierBillService::class)->postSupplierBill($bill);

        $entry = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();

        $this->assertNotNull($entry, 'Journal entry not created for supplier bill posting');

        // Assert balanced totals
        $this->assertEquals(
            $entry->totalDebit(),
            $entry->totalCredit(),
            'Journal entry is not balanced'
        );

        // Assert correct accounts involved
        $inventoryAccount = Account::where('code', '1200')->first();
        $apAccount        = Account::where('code', '2000')->first();

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $inventoryAccount->id,
            'debit'            => 100.00,
            'credit'           => 0.00,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $apAccount->id,
            'debit'            => 0.00,
            'credit'           => 100.00,
        ]);
    }

    public function invoice_creation_creates_sales_and_cogs_entries(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        // --------------------------------------------------------------
        // Prepare data
        // --------------------------------------------------------------
        $customer = \App\Models\Customer::factory()->create();
        $product  = \App\Models\Product::factory()->create([
            'purchase_price' => 20,
            'selling_price'  => 50,
        ]);

        $order = \App\Models\Order::create([
            'customer_id'    => $customer->id,
            'status'         => 'completed',
            'order_date'     => now(),
            'total_amount'   => 150, // 3 * 50
            'fulfillment_location_id'   => null,
            'fulfillment_location_type' => null,
        ]);

        // 3 units
        \App\Models\OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 3,
            'unit_price' => 50,
            'subtotal'   => 150,
        ]);

        $invoiceService = app(\App\Services\InvoiceService::class);
        $invoice        = $invoiceService->generateFromOrder($order);

        // Observer should have created a journal entry
        $entry = \App\Models\JournalEntry::where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->id)
            ->first();

        $this->assertNotNull($entry, 'Journal entry not created for Invoice');
        $this->assertEquals($entry->totalDebit(), $entry->totalCredit(), 'Invoice journal not balanced');

        // Expected amounts
        $saleTotal  = 150.00;
        $costTotal  = 3 * 20; // 60.00

        $ar   = \App\Models\Account::where('code', '1100')->first();
        $sales= \App\Models\Account::where('code', '4000')->first();
        $cogs = \App\Models\Account::where('code', '5000')->first();
        $inv  = \App\Models\Account::where('code', '1200')->first();

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $ar->id,
            'debit'            => $saleTotal,
            'credit'           => 0,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $sales->id,
            'debit'            => 0,
            'credit'           => $saleTotal,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $cogs->id,
            'debit'            => $costTotal,
            'credit'           => 0,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id'       => $inv->id,
            'debit'            => 0,
            'credit'           => $costTotal,
        ]);
    }

    /** @test */
    public function grn_posting_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $vendor    = \App\Models\Vendor::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();
        $product   = \App\Models\Product::factory()->create(['purchase_price' => 5]);

        $supply = \App\Models\Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 50,
        ]);
        \App\Models\SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id'=> $product->id,
            'quantity'  => 10,
            'unit_cost' => 5,
            'subtotal'  => 50,
        ]);
        $grn = \App\Models\Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        $grn->update(['status' => 'posted']);
        $grn->update(['notes' => 'touch']); // unrelated change should NOT duplicate the bill

        // A GRN raises exactly one supplier bill, however many times it is
        // saved afterwards - GrnObserver bails out when one already exists.
        $billCount = \App\Models\SupplierBill::where('grn_id', $grn->id)->count();

        $this->assertEquals(1, $billCount, 'GRN supplier bill duplicated');

        // And posting that bill raises exactly one journal entry.
        $bill = \App\Models\SupplierBill::where('grn_id', $grn->id)->firstOrFail();
        app(SupplierBillService::class)->postSupplierBill($bill);

        $entryCount = \App\Models\JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)->count();

        $this->assertEquals(1, $entryCount, 'Supplier bill journal entry duplicated');

        // Posting a second time is refused rather than doubling the liability.
        $this->expectException(\Exception::class);
        app(SupplierBillService::class)->postSupplierBill($bill->fresh());
    }

    /** @test */
    public function invoice_journal_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $customer = \App\Models\Customer::factory()->create();
        $product  = \App\Models\Product::factory()->create(['purchase_price'=> 15, 'selling_price'=> 30]);

        $order = \App\Models\Order::create([
            'customer_id' => $customer->id,
            'status'      => 'completed',
            'order_date'  => now(),
            'total_amount'=> 60,
        ]);
        \App\Models\OrderItem::create([
            'order_id'=> $order->id,
            'product_id'=>$product->id,
            'quantity'=>2, 'unit_price'=>30, 'subtotal'=>60,
        ]);

        $svc = app(\App\Services\InvoiceService::class);
        $invoice = $svc->generateFromOrder($order);
        // Call again
        $svc->generateFromOrder($order);

        $count = \App\Models\JournalEntry::where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->id)->count();
        $this->assertEquals(1, $count, 'Invoice journal duplicated');
    }

    /** @test */
    public function return_completion_creates_reversal_entries_and_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        // Create invoice first
        $customer = \App\Models\Customer::factory()->create();
        $product  = \App\Models\Product::factory()->create(['purchase_price'=> 8, 'selling_price'=> 20]);
        $order = \App\Models\Order::create([
            'customer_id'=>$customer->id,
            'status'=>'completed',
            'order_date'=> now(),
            'total_amount'=> 20,
        ]);
        \App\Models\OrderItem::create([
            'order_id'=>$order->id,
            'product_id'=>$product->id,
            'quantity'=>1,
            'unit_price'=>20,
            'subtotal'=>20,
        ]);
        $invoice = app(\App\Services\InvoiceService::class)->generateFromOrder($order);

        $warehouse = \App\Models\Warehouse::factory()->create();

        // A return starts pending. Recording one moves nothing and posts
        // nothing; approving it is the step that acts.
        $returnTransaction = \App\Models\StockTransaction::create([
            'product_id' => $product->id,
            'transaction_type' => \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN,
            'direction' => 'inbound',
            'quantity' => 1,
            'unit_cost' => 20,
            'total_cost' => 20,
            'status' => \App\Models\StockTransaction::STATUS_PENDING,
            'location_type' => \App\Models\Warehouse::class,
            'location_id' => $warehouse->id,
            'reference_type' => $invoice->getMorphClass(),
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'notes' => 'Damaged',
        ]);

        $this->assertSame(
            0,
            \App\Models\JournalEntry::where('source_type', $returnTransaction->getMorphClass())
                ->where('source_id', $returnTransaction->id)->count(),
            'Recording a return should not reach the ledger'
        );

        // Approving raises the credit note that carries the financial side.
        $returnTransaction->approve(1);

        $creditNote = $returnTransaction->fresh()->creditNote;
        $this->assertNotNull($creditNote, 'Credit note not raised on approval');

        // Posting the note writes the reversing entry against the sale.
        $handler = app(\App\Services\ReturnJournalHandler::class);
        $entry   = $handler->createCustomerReturnJournal($creditNote);

        $this->assertNotNull($entry, 'Return journal missing');
        $this->assertEquals($entry->totalDebit(), $entry->totalCredit());
        $this->assertTrue((bool) $entry->is_reverse, 'Return journal should be marked as a reversal');

        $count = \App\Models\JournalEntry::where('source_type', $creditNote->getMorphClass())
            ->where('source_id', $creditNote->id)->count();
        $this->assertEquals(1, $count, 'Return journal duplicated');
    }
} 