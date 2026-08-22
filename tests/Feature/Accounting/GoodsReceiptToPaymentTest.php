<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Models\Grn;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\SupplierBillService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The purchase side, end to end, through the GR-IR clearing account.
 */
class GoodsReceiptToPaymentTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;
    private Vendor $vendor;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->ledger    = app(LedgerService::class);
        $this->vendor    = Vendor::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product   = Product::factory()->create(['purchase_price' => 10]);
    }

    public function test_receiving_goods_debits_inventory_and_parks_the_other_side_in_gr_ir(): void
    {
        $this->receiveGoods();

        // The business owns the goods the moment they arrive, whether or not
        // anyone has got round to the paperwork.
        $this->assertBalance(AccountRole::Inventory, '100.00');
        $this->assertBalance(AccountRole::GoodsReceivedNotInvoiced, '-100.00');

        // Nothing is owed to the vendor yet: they have not billed.
        $this->assertBalance(AccountRole::AccountsPayable, '0.00');

        $this->assertTrialBalances();
    }

    public function test_inventory_value_is_held_against_the_receiving_location(): void
    {
        $this->receiveGoods();

        $byLocation = $this->ledger->locationBalances(AccountRole::Inventory);
        $key = $this->warehouse->getMorphClass() . ':' . $this->warehouse->id;

        $this->assertArrayHasKey($key, $byLocation->all());
        $this->assertSame('100.00', $byLocation[$key]['balance']->toDecimal());

        // Per-location value is a grouping of the one inventory account, so
        // the parts necessarily add up to the whole.
        $this->assertSame(
            $this->ledger->balance(AccountRole::Inventory)->toDecimal(),
            $byLocation[$key]['balance']->toDecimal(),
        );
    }

    public function test_posting_the_bill_clears_gr_ir_into_accounts_payable(): void
    {
        $grn = $this->receiveGoods();
        $bill = $grn->fresh()->supplierBill;

        $this->assertNotNull($bill, 'Receiving goods should raise a draft supplier bill.');

        app(SupplierBillService::class)->postSupplierBill($bill);

        // GR-IR has done its job and returns to zero.
        $this->assertBalance(AccountRole::GoodsReceivedNotInvoiced, '0.00');
        $this->assertBalance(AccountRole::AccountsPayable, '-100.00');

        // Inventory is untouched by the bill: the goods were taken into stock
        // when they arrived, not when the paperwork caught up.
        $this->assertBalance(AccountRole::Inventory, '100.00');

        $this->assertTrialBalances();
    }

    public function test_the_payable_is_reconcilable_against_the_vendor(): void
    {
        $grn = $this->receiveGoods();
        app(SupplierBillService::class)->postSupplierBill($grn->fresh()->supplierBill);

        $byVendor = $this->ledger->partyBalances(AccountRole::AccountsPayable);
        $key = $this->vendor->getMorphClass() . ':' . $this->vendor->id;

        $this->assertArrayHasKey($key, $byVendor->all(), 'Every payable line must name its vendor.');
        $this->assertSame('-100.00', $byVendor[$key]['balance']->toDecimal());
    }

    public function test_paying_the_bill_settles_the_payable_against_cash(): void
    {
        $grn = $this->receiveGoods();
        $service = app(SupplierBillService::class);

        $bill = $service->postSupplierBill($grn->fresh()->supplierBill);
        $service->paySupplierBill($bill->fresh());

        $this->assertBalance(AccountRole::AccountsPayable, '0.00');
        $this->assertBalance(AccountRole::Cash, '-100.00');
        $this->assertBalance(AccountRole::Inventory, '100.00');

        $this->assertTrialBalances();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function receiveGoods(): Grn
    {
        $supply = Supply::create([
            'vendor_id'    => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => 100,
        ]);

        SupplyItem::create([
            'supply_id'  => $supply->id,
            'product_id' => $this->product->id,
            'quantity'   => 10,
            'unit_cost'  => 10,
            'subtotal'   => 100,
        ]);

        $grn = Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        $grn->update(['status' => 'posted']);

        return $grn;
    }

    private function assertBalance(AccountRole $role, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->ledger->balance($role)->toDecimal(),
            sprintf('%s (%s) is not where it should be.', $role->label(), $role->code()),
        );
    }

    private function assertTrialBalances(): void
    {
        $totals = $this->ledger->trialBalanceTotals();

        $this->assertTrue(
            $totals['balanced'],
            sprintf(
                'Trial balance is out by %s (debits %s, credits %s).',
                $totals['difference']->toDecimal(),
                $totals['debit']->toDecimal(),
                $totals['credit']->toDecimal(),
            ),
        );
    }
}
