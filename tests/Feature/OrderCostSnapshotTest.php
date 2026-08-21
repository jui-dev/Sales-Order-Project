<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Grn;
use App\Models\Order;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an order cost us is fixed when the order is placed.
 *
 * order_items snapshotted unit_price from the start but never the cost, so
 * OrderItem::getUnitCostAttribute fell back to the product's *current*
 * purchase_price. Posting a goods receipt overwrites that column outright, so
 * every order already on file silently re-costed itself against the newest
 * delivery - rewriting profit on the report and in the ledger.
 */
class OrderCostSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These describe the full model, where receiving goods re-derives what
        // a product costs and sells for. Simple mode deliberately stops that -
        // the price the user typed stands - and SimplePricingTest covers it.
        config(['pricing.simple_mode' => false]);
    }

    private function orderFor(Product $product, float $unitPrice, int $quantity = 2): Order
    {
        $retailer = Retailer::factory()->create();

        return app(OrderService::class)->createWithItems([
            'customer_id' => Customer::factory()->create()->id,
            'order_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'fulfillment_location_id' => $retailer->id,
                    'fulfillment_location_type' => 'retailer',
                ],
            ],
        ]);
    }

    /** Receive goods, which is the only thing that moves products.purchase_price. */
    private function receive(Product $product, float $unitCost, int $quantity = 5): void
    {
        $supply = Supply::factory()->create([
            'vendor_id' => Vendor::factory(),
            'warehouse_id' => Warehouse::factory(),
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $unitCost * $quantity,
        ]);

        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');
    }

    public function test_an_order_line_captures_the_cost_at_the_time_it_was_placed(): void
    {
        $product = Product::factory()->create(['purchase_price' => 400.00, 'markup' => 25]);

        $order = $this->orderFor($product, 500.00);

        $this->assertEquals(400.00, $order->items->first()->unit_cost);
    }

    public function test_receiving_goods_later_does_not_re_cost_an_existing_order(): void
    {
        $product = Product::factory()->create(['purchase_price' => 400.00, 'markup' => 25]);
        $order = $this->orderFor($product, 500.00, 2);

        // The regression: a cheaper delivery arrives after the sale.
        $this->receive($product, 200.00);

        $product->refresh();
        $this->assertEquals(200.00, $product->purchase_price, 'Guard: the receipt did move the product cost.');

        $item = $order->items()->first();
        $this->assertEquals(400.00, $item->unit_cost, 'The order must keep the cost it was placed at.');
        $this->assertEquals(500.00, $item->unit_price, 'And the price it was sold at.');

        // (500 - 400) * 2 = 200. Against today's cost it would read 600.
        $this->assertEquals(200.00, $item->profit, 'Profit must not follow the new cost.');
    }

    public function test_confirming_an_order_no_longer_overwrites_the_products_gross_profit(): void
    {
        // Sold well above list, so the two former definitions of gross_profit
        // disagree: selling - purchase = 25, but this line's margin is 100.
        $product = Product::factory()->create([
            'purchase_price' => 100.00,
            'selling_price' => 125.00,
            'markup' => 25,
        ]);

        $order = $this->orderFor($product, 200.00, 1);
        $order->update(['status' => 'confirmed']);

        $product->refresh();

        $this->assertEquals(
            25.00,
            $product->gross_profit,
            'gross_profit is the catalogue margin, not the last order line sold.'
        );
    }
}
