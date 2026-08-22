<?php

namespace Tests\Feature;

use App\Models\Grn;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Receiving goods books them once, and books the stock and the ledger together.
 *
 * GrnService::postStock() opened with a guard on $grn->posted_at and closed by
 * stamping it only if the column existed. It did not, so the guard had never
 * fired - and the transfer item rows sat outside the one check that did work,
 * so a re-post duplicated them while the stock correctly stayed put.
 */
class GoodsReceiptPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_posting_a_receipt_twice_books_it_once(): void
    {
        [$grn, $product, $warehouse] = $this->delivery(10, 10.00);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $this->assertSame(10, $this->stockAt($product, $warehouse));

        // Back to draft and forward again: the guard is what has to stop the
        // second posting, not the fact that nobody normally does this.
        $grn->fresh()->update(['status' => 'draft']);
        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $this->assertSame(10, $this->stockAt($product, $warehouse), 'The delivery was booked twice.');

        $this->assertSame(
            1,
            StockTransaction::where('reference_type', Grn::class)->where('reference_id', $grn->id)->count(),
        );

        // The transfer lines used to be created outside the guard, so they
        // multiplied on every call.
        $this->assertSame(1, StockTransferItem::count(), 'The transfer lines were duplicated.');
        $this->assertSame(1, StockTransfer::count());
    }

    public function test_two_deliveries_on_one_day_are_two_transfers(): void
    {
        // The transfer used to be a firstOrCreate keyed on vendor, warehouse
        // and date, so two deliveries from one vendor into one warehouse on one
        // day shared a record and piled their items together.
        $vendor = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();

        [$first] = $this->delivery(5, 10.00, $vendor, $warehouse);
        [$second] = $this->delivery(7, 10.00, $vendor, $warehouse);

        app(GrnService::class)->transitionStatus($first->id, 'posted');
        app(GrnService::class)->transitionStatus($second->id, 'posted');

        $this->assertSame(2, StockTransfer::count());
        $this->assertSame(2, StockTransferItem::count());
    }

    /**
     * The stock and the ledger move on one event.
     *
     * The observer posted the ledger and the service posted the stock, so a
     * status set anywhere but through the service booked the value of goods
     * that never reached a shelf - the exact drift the goods-receipt rule
     * exists to close.
     */
    public function test_setting_the_status_directly_still_moves_the_stock(): void
    {
        [$grn, $product, $warehouse] = $this->delivery(4, 10.00);

        $grn->update(['status' => 'posted']);

        $this->assertSame(4, $this->stockAt($product, $warehouse));
    }

    private function stockAt(Product $product, Warehouse $warehouse): int
    {
        return (int) ProductStock::where('product_id', $product->id)
            ->where('location_type', Warehouse::class)
            ->where('location_id', $warehouse->id)
            ->sum('quantity');
    }

    /**
     * @return array{0:Grn,1:Product,2:Warehouse}
     */
    private function delivery(int $quantity, float $unitCost, ?Vendor $vendor = null, ?Warehouse $warehouse = null): array
    {
        $vendor ??= Vendor::factory()->create();
        $warehouse ??= Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => $unitCost]);

        $supply = Supply::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'supply_date' => now(),
            'status' => 'pending',
            'total_cost' => $quantity * $unitCost,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $quantity * $unitCost,
        ]);

        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        return [$grn, $product, $warehouse];
    }
}
