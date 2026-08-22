<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Accounting\Reconciliation\ReconciliationService;
use App\Models\Customer;
use App\Models\Grn;
use App\Models\Invoice;
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
 * The general ledger against everything else that claims to know the same
 * numbers. This is what control accounts are for.
 */
class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private ReconciliationService $reconcile;
    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->reconcile = app(ReconciliationService::class);
        $this->ledger    = app(LedgerService::class);
    }

    public function test_the_chart_can_serve_every_posting_rule(): void
    {
        // A rule whose account is missing fails at the moment somebody tries
        // to receive goods or raise an invoice. It should be knowable before.
        $this->assertSame([], $this->reconcile->unresolvableRoles());
    }

    public function test_an_empty_ledger_reconciles(): void
    {
        foreach ($this->reconcile->run() as $check) {
            $this->assertTrue($check->passed, $check->title . ' failed on an empty ledger.');
        }
    }

    public function test_a_full_purchase_and_sale_cycle_reconciles(): void
    {
        $vendor    = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create(['purchase_price' => 10]);
        $customer  = Customer::factory()->create();

        // --- buy 10 at 10 -------------------------------------------------
        $supply = Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 100,
        ]);

        SupplyItem::create([
            'supply_id'  => $supply->id,
            'product_id' => $product->id,
            'quantity'   => 10,
            'unit_cost'  => 10,
            'subtotal'   => 100,
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

        // --- sell some of it ----------------------------------------------
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-RECON-1',
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'invoice_date'   => now(),
            'subtotal'       => 300,
            'tax'            => 45,
            'discount'       => 0,
            'total'          => 345,
            'payment_status' => 'unpaid',
        ]);

        Payment::create([
            'invoice_id'   => $invoice->id,
            'amount'       => 200,
            'method'       => 'cash',
            'payment_date' => now(),
            'status'       => 'completed',
        ]);

        // --- everything must tie out --------------------------------------
        foreach ($this->reconcile->run() as $check) {
            $this->assertTrue($check->passed, sprintf(
                "%s failed: ledger %s, expected %s, out by %s.\n%s",
                $check->title,
                $check->ledger->toDecimal(),
                $check->expected->toDecimal(),
                $check->difference->toDecimal(),
                json_encode(array_map(fn ($r) => [
                    $r['label'],
                    $r['ledger']->toDecimal(),
                    $r['expected']->toDecimal(),
                ], $check->discrepancies())),
            ));
        }
    }

    public function test_receiving_without_billing_leaves_the_value_in_gr_ir(): void
    {
        $vendor    = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create(['purchase_price' => 25]);

        $supply = Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 250,
        ]);

        SupplyItem::create([
            'supply_id'  => $supply->id,
            'product_id' => $product->id,
            'quantity'   => 10,
            'unit_cost'  => 25,
            'subtotal'   => 250,
        ]);

        $grn = Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        // The goods are on the shelves and nobody has billed for them, which
        // is precisely what GR-IR is meant to say.
        $this->assertSame('-250.00', $this->ledger->balance(AccountRole::GoodsReceivedNotInvoiced)->toDecimal());

        foreach ($this->reconcile->run() as $check) {
            $this->assertTrue($check->passed, $check->title . ' failed: out by ' . $check->difference->toDecimal());
        }
    }
}
