<?php

namespace Tests\Feature\Accounting;

use App\Models\Grn;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SupplierBill;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\SupplierBillService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Throwing the ledger away and replaying it gets the same books back.
 *
 * That is the claim accounting:rebuild exists to make good, and the reason a
 * change to a posting rule can be applied to history. It was not quite true:
 * discardLedger() nulls the document-to-entry foreign keys before deleting the
 * entries they name, and nothing put them back, so every document came out of
 * a rebuild unable to find its own journal.
 */
class RebuildLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rebuild_leaves_each_document_pointing_at_its_entry(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $bill = $this->postedAndPaidBill();

        $purchaseBefore = $bill->purchase_journal_id;
        $paymentBefore = $bill->payment_journal_id;

        $this->assertNotNull($purchaseBefore);
        $this->assertNotNull($paymentBefore);

        $this->artisan('accounting:rebuild', ['--force' => true])->assertSuccessful();

        $rebuilt = $bill->fresh();

        $this->assertNotNull($rebuilt->purchase_journal_id, 'The bill lost its purchase journal in the rebuild.');
        $this->assertNotNull($rebuilt->payment_journal_id, 'The bill lost its payment journal in the rebuild.');

        // The entries are new rows - the old ones were deleted - but they are
        // the entries for the same document and rule.
        $this->assertSame(
            'supplier_bill.purchase',
            JournalEntry::find($rebuilt->purchase_journal_id)->rule_key,
        );
        $this->assertSame(
            'supplier_bill.payment',
            JournalEntry::find($rebuilt->payment_journal_id)->rule_key,
        );

        $this->assertNotNull($rebuilt->payment->fresh()->payment_journal_id);
    }

    private function postedAndPaidBill(): SupplierBill
    {
        $vendor = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 10]);

        $supply = Supply::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'supply_date' => now(),
            'status' => 'pending',
            'total_cost' => 100,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 10,
            'subtotal' => 100,
        ]);

        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        // Through the service: it is what moves the stock, while GrnObserver
        // is what posts the ledger. Setting the status on the model alone
        // books the value without the goods, and the rebuild's inventory
        // check is right to refuse that.
        app(\App\Services\GrnService::class)->transitionStatus($grn->id, 'posted');

        $bill = SupplierBill::where('grn_id', $grn->id)->firstOrFail();

        $service = app(SupplierBillService::class);
        $service->postSupplierBill($bill);
        $service->paySupplierBill($bill->fresh());

        return $bill->fresh();
    }
}
