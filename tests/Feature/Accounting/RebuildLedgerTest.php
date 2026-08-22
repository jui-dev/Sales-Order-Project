<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Models\Customer;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\SupplierBillService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ledger holds nothing the documents do not already know.
 */
class RebuildLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ledger_can_be_deleted_and_reproduced_from_the_documents(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $this->buildACycle();

        $ledger = app(LedgerService::class);
        $before = $this->snapshot($ledger);
        $entryCount = JournalEntry::count();

        $this->assertGreaterThan(0, $entryCount, 'The cycle should have posted something.');

        $this->artisan('accounting:rebuild', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame($entryCount, JournalEntry::count(), 'Replaying should raise the same number of entries.');
        $this->assertSame($before, $this->snapshot($ledger), 'Replaying should reproduce the same balances.');
    }

    /** @return array<string,string> */
    private function snapshot(LedgerService $ledger): array
    {
        $out = [];

        foreach (AccountRole::cases() as $role) {
            $out[$role->value] = $ledger->balance($role)->toDecimal();
        }

        return $out;
    }

    private function buildACycle(): void
    {
        $vendor    = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create(['purchase_price' => 12]);
        $customer  = Customer::factory()->create();

        $supply = Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 120,
        ]);

        SupplyItem::create([
            'supply_id'  => $supply->id,
            'product_id' => $product->id,
            'quantity'   => 10,
            'unit_cost'  => 12,
            'subtotal'   => 120,
        ]);

        $grn = Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $bills = app(SupplierBillService::class);
        $bill = $bills->postSupplierBill($grn->fresh()->supplierBill);
        $bills->paySupplierBill($bill->fresh());

        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-REBUILD-1',
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'invoice_date'   => now(),
            'subtotal'       => 500,
            'tax'            => 75,
            'discount'       => 25,
            'total'          => 550,
            'payment_status' => 'unpaid',
        ]);

        Payment::create([
            'invoice_id'   => $invoice->id,
            'amount'       => 300,
            'method'       => 'cash',
            'payment_date' => now(),
            'status'       => 'completed',
        ]);
    }
}
