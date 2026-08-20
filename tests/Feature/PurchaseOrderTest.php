<?php

namespace Tests\Feature;

use App\Models\Grn;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\Warehouse;
use App\Services\GrnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The warehouse's request to a vendor, upstream of Supplies.
 *
 * Covers the lifecycle gates, the price list constraint that makes an order
 * able to cost itself, and the hand-off into the existing supply chain.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create(['purchase_price' => 0, 'selling_price' => 0, 'markup' => 25]);

        VendorProduct::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'unit_cost' => 47.50,
        ]);
    }

    private function createOrder(int $quantity = 10, float $unitCost = 47.50): PurchaseOrder
    {
        $this->post(route('purchase-orders.store'), [
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost],
            ],
        ])->assertRedirect();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    private function sentOrder(int $quantity = 10): PurchaseOrder
    {
        $order = $this->createOrder($quantity);
        $this->patch(route('purchase-orders.approve', $order));
        $this->patch(route('purchase-orders.send', $order));

        return $order->fresh();
    }

    public function test_an_order_starts_as_a_draft_with_its_total_calculated(): void
    {
        $order = $this->createOrder(10, 47.50);

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->assertEquals(475.00, $order->total_cost);
        $this->assertSame(10, $order->items->first()->quantity_ordered);
        $this->assertSame(0, $order->items->first()->quantity_received);
    }

    public function test_an_order_cannot_ask_a_vendor_for_a_product_they_do_not_carry(): void
    {
        $stranger = Product::factory()->create();

        $this->post(route('purchase-orders.store'), [
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'products' => [
                ['product_id' => $stranger->id, 'quantity' => 1, 'unit_cost' => 5.00],
            ],
        ])->assertSessionHasErrors('products.0.product_id');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_the_lifecycle_runs_draft_to_approved_to_sent(): void
    {
        $order = $this->createOrder();

        $this->patch(route('purchase-orders.approve', $order))->assertRedirect();
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $order->status);
        $this->assertNotNull($order->approved_at);

        $this->patch(route('purchase-orders.send', $order))->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $order->fresh()->status);
    }

    public function test_an_order_cannot_be_sent_before_it_is_approved(): void
    {
        $order = $this->createOrder();

        $this->patch(route('purchase-orders.send', $order));

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->fresh()->status);
    }

    public function test_an_approved_order_can_no_longer_be_edited(): void
    {
        $order = $this->createOrder();
        $this->patch(route('purchase-orders.approve', $order));

        $this->put(route('purchase-orders.update', $order), [
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 999, 'unit_cost' => 1.00],
            ],
        ]);

        $this->assertSame(10, $order->fresh()->items->first()->quantity_ordered);
    }

    public function test_a_supply_cannot_be_recorded_against_an_unsent_order(): void
    {
        $order = $this->createOrder();

        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 47.50]],
        ]);

        $this->assertSame(0, Supply::count());
    }

    public function test_a_full_delivery_closes_the_order(): void
    {
        $order = $this->sentOrder(10);

        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 47.50]],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->status);
        $this->assertSame(10, $order->items->first()->quantity_received);
        $this->assertSame(0, $order->items->first()->outstanding());
    }

    public function test_a_short_delivery_leaves_the_order_partially_received(): void
    {
        $order = $this->sentOrder(10);

        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 4, 'unit_cost' => 47.50]],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->status);
        $this->assertSame(6, $order->items->first()->outstanding());

        // The rest turns up later.
        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 6, 'unit_cost' => 47.50]],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->status);
        $this->assertSame(2, $order->supplies()->count());
    }

    public function test_the_recorded_supply_inherits_the_orders_vendor_and_warehouse(): void
    {
        $order = $this->sentOrder(5);

        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 47.50]],
        ]);

        $supply = Supply::firstOrFail();
        $this->assertSame($order->id, $supply->purchase_order_id);
        $this->assertSame($this->vendor->id, $supply->vendor_id);
        $this->assertSame($this->warehouse->id, $supply->warehouse_id);
        $this->assertSame('pending', $supply->status);
    }

    public function test_ordering_and_receiving_only_prices_the_product_at_the_grn(): void
    {
        $order = $this->sentOrder(5);

        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 47.50]],
        ]);

        // Ordered and delivered, but not yet received into stock.
        $this->assertEquals(0, $this->product->fresh()->selling_price);

        $supply = Supply::firstOrFail();
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'draft']);
        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $product = $this->product->fresh();
        $this->assertEquals(47.50, $product->purchase_price);
        $this->assertEquals(59.38, $product->selling_price);
    }

    public function test_a_cancelled_order_stays_cancelled(): void
    {
        $order = $this->sentOrder(5);

        $this->patch(route('purchase-orders.cancel', $order))->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);

        // And can no longer be received against.
        $this->post(route('purchase-orders.record-supply', $order), [
            'supply_date' => now()->toDateString(),
            'products' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 47.50]],
        ]);

        $this->assertSame(0, Supply::count());
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_the_price_list_endpoint_returns_the_vendors_products_and_costs(): void
    {
        $this->get(route('purchase-orders.vendor-products', $this->vendor))
            ->assertOk()
            ->assertJsonFragment(['id' => $this->product->id, 'unit_cost' => '47.50']);
    }

    public function test_the_create_form_is_stepped_vendor_then_items_then_delivery(): void
    {
        $html = $this->get(route('purchase-orders.create'))->assertOk()->getContent();

        // Steps appear in the order the decisions are made.
        $vendorAt   = strpos($html, 'id="vendor_id"');
        $itemsAt    = strpos($html, 'id="items-section"');
        $deliveryAt = strpos($html, 'id="delivery-section"');

        $this->assertNotFalse($vendorAt);
        $this->assertNotFalse($itemsAt);
        $this->assertNotFalse($deliveryAt);
        $this->assertLessThan($itemsAt, $vendorAt, 'Vendor must come before items.');
        $this->assertLessThan($deliveryAt, $itemsAt, 'Items must come before the delivery step.');

        // Items and delivery start collapsed; nothing can be answered before a vendor is picked.
        $this->assertStringContainsString('class="card mb-4 d-none" id="items-section"', $html);
        $this->assertStringContainsString('class="card mb-4 d-none" id="delivery-section"', $html);
    }

    public function test_the_index_and_detail_pages_render(): void
    {
        $order = $this->createOrder();

        $this->get(route('purchase-orders.index'))->assertOk()->assertSee($order->code);
        $this->get(route('purchase-orders.show', $order))->assertOk()->assertSee('Purchase Order');
        $this->get(route('purchase-orders.create'))->assertOk();
        $this->get(route('purchase-orders.edit', $order))->assertOk();
    }
}
