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
use Illuminate\Testing\TestResponse;
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

        // The pivot records that the vendor carries the product; what they
        // charge lives on their purchase price list, where it is dated.
        VendorProduct::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
        ]);

        $lists = app(\App\Services\Pricing\PriceListService::class);
        $lists->setPrice($lists->forVendor($this->vendor), $this->product, 47.50);
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

    /**
     * A delivery against an order, recorded the way the screens do it: through
     * the supply form, carrying the order it belongs to.
     */
    private function recordSupply(PurchaseOrder $order, int $quantity, ?Product $product = null): TestResponse
    {
        $product ??= $this->product;

        return $this->post(route('supplies.store'), [
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'warehouse_id' => $order->warehouse_id,
            'supply_date' => now()->toDateString(),
            'products' => [
                ['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => 47.50],
            ],
        ]);
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

        $this->recordSupply($order, 5);

        $this->assertSame(0, Supply::count());
    }

    public function test_a_full_delivery_closes_the_order(): void
    {
        $order = $this->sentOrder(10);

        $this->recordSupply($order, 10)->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->status);
        $this->assertSame(10, $order->items->first()->quantity_received);
        $this->assertSame(0, $order->items->first()->outstanding());
    }

    public function test_a_short_delivery_leaves_the_order_partially_received(): void
    {
        $order = $this->sentOrder(10);

        $this->recordSupply($order, 4)->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->status);
        $this->assertSame(6, $order->items->first()->outstanding());

        // The rest turns up later.
        $this->recordSupply($order, 6)->assertRedirect();

        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->status);
        $this->assertSame(2, $order->supplies()->count());
    }

    public function test_the_recorded_supply_inherits_the_orders_vendor_and_warehouse(): void
    {
        $order = $this->sentOrder(5);

        $this->recordSupply($order, 5);

        $supply = Supply::firstOrFail();
        $this->assertSame($order->id, $supply->purchase_order_id);
        $this->assertSame($this->vendor->id, $supply->vendor_id);
        $this->assertSame($this->warehouse->id, $supply->warehouse_id);
        $this->assertSame('pending', $supply->status);
    }

    public function test_a_delivery_cannot_bring_a_product_that_was_not_ordered(): void
    {
        $order = $this->sentOrder(5);
        $stranger = Product::factory()->create();

        $this->recordSupply($order, 5, $stranger)
            ->assertSessionHasErrors('products.0.product_id');

        $this->assertSame(0, Supply::count());
    }

    /**
     * Goods are recorded against what was ordered, so a supply with no order
     * behind it is not a shortcut - it is a missing answer.
     */
    public function test_a_supply_cannot_be_recorded_without_an_order_behind_it(): void
    {
        $this->post(route('supplies.store'), [
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'supply_date' => now()->toDateString(),
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 47.50],
            ],
        ])->assertSessionHasErrors('purchase_order_id');

        $this->assertSame(0, Supply::count());
    }

    public function test_the_supply_form_sends_you_to_pick_an_order_when_none_is_named(): void
    {
        $this->get(route('supplies.create'))
            ->assertRedirect(route('supplies.purchase-orders'));
    }

    public function test_ordering_and_receiving_only_prices_the_product_at_the_grn(): void
    {
        // What this pins - that ordering never reprices, only receiving does -
        // needs receiving to reprice at all, which is the full model. Simple
        // mode goes further and lets neither move a price; see SimplePricingTest.
        config(['pricing.simple_mode' => false]);

        $order = $this->sentOrder(5);

        $this->recordSupply($order, 5);

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
        $this->recordSupply($order, 5);

        $this->assertSame(0, Supply::count());
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_the_supply_form_is_prefilled_from_the_order(): void
    {
        $order = $this->sentOrder(10);

        $response = $this->get(route('supplies.create', ['purchase_order' => $order->id]));

        $response->assertOk();
        $this->assertSame($order->id, $response->viewData('purchaseOrder')->id);

        // Vendor and warehouse are pinned to the order rather than chosen again.
        $response->assertSee('name="vendor_id" value="'.$order->vendor_id.'"', false);
        $response->assertSee('name="warehouse_id" value="'.$order->warehouse_id.'"', false);
        $response->assertSee('name="purchase_order_id" value="'.$order->id.'"', false);

        $lines = $response->viewData('prefillLines');
        $this->assertCount(1, $lines);
        $this->assertSame($this->product->id, $lines[0]['product_id']);
        $this->assertSame(10, $lines[0]['quantity']);
        $this->assertEquals(47.50, $lines[0]['unit_cost']);
    }

    public function test_the_supply_form_prefills_only_what_is_still_outstanding(): void
    {
        $order = $this->sentOrder(10);
        $this->recordSupply($order, 4);

        $lines = $this->get(route('supplies.create', ['purchase_order' => $order->id]))
            ->assertOk()
            ->viewData('prefillLines');

        $this->assertSame(6, $lines[0]['quantity']);
    }

    /**
     * The form shows the order's lines rather than asking for them, so there is
     * no product picker, no way to add a line and nothing to search a catalogue
     * with. What gets submitted is the order's own figures, carried hidden.
     */
    public function test_the_supply_form_offers_no_way_to_change_or_add_a_line(): void
    {
        $order = $this->sentOrder(10);

        $response = $this->get(route('supplies.create', ['purchase_order' => $order->id]))
            ->assertOk();

        $response->assertDontSee('Quick add a product');
        $response->assertDontSee('id="add-item"', false);
        $response->assertDontSee('class="form-select product-select"', false);

        $response->assertSee('name="products[0][product_id]" value="'.$this->product->id.'"', false);
        $response->assertSee('name="products[0][quantity]" value="10"', false);
    }

    public function test_a_supply_cannot_bring_more_than_the_order_is_waiting_on(): void
    {
        $order = $this->sentOrder(5);

        $this->recordSupply($order, 6)->assertSessionHasErrors('products.0.quantity');

        $this->assertSame(0, Supply::count());
        $this->assertSame(PurchaseOrder::STATUS_SENT, $order->fresh()->status);
    }

    public function test_a_supply_cannot_bring_a_cost_the_order_did_not_agree(): void
    {
        $order = $this->sentOrder(5);

        $this->post(route('supplies.store'), [
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'warehouse_id' => $order->warehouse_id,
            'supply_date' => now()->toDateString(),
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 50.00],
            ],
        ])->assertSessionHasErrors('products.0.unit_cost');

        $this->assertSame(0, Supply::count());
    }

    /**
     * Split across two lines, the same product could slip past a per-line cap -
     * so what is outstanding is checked against the product's whole total.
     */
    public function test_the_same_product_twice_cannot_together_exceed_what_is_outstanding(): void
    {
        $order = $this->sentOrder(10);

        $this->post(route('supplies.store'), [
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'warehouse_id' => $order->warehouse_id,
            'supply_date' => now()->toDateString(),
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 6, 'unit_cost' => 47.50],
                ['product_id' => $this->product->id, 'quantity' => 6, 'unit_cost' => 47.50],
            ],
        ])->assertSessionHasErrors('products.1.quantity');

        $this->assertSame(0, Supply::count());
    }

    public function test_the_supply_form_refuses_an_order_that_cannot_be_received_against(): void
    {
        $order = $this->createOrder();

        $this->get(route('supplies.create', ['purchase_order' => $order->id]))
            ->assertRedirect(route('purchase-orders.show', $order->id));
    }

    public function test_the_requested_orders_page_lists_only_orders_awaiting_a_delivery(): void
    {
        $sent = $this->sentOrder(5);
        $draft = $this->createOrder(3);

        $this->get(route('supplies.purchase-orders'))
            ->assertOk()
            ->assertSee($sent->code)
            ->assertDontSee($draft->code);
    }

    public function test_a_fully_received_order_drops_off_the_requested_list(): void
    {
        $order = $this->sentOrder(5);
        $this->recordSupply($order, 5);

        $this->get(route('supplies.purchase-orders'))
            ->assertOk()
            ->assertDontSee($order->code);
    }

    public function test_the_supplies_list_counts_the_orders_awaiting_a_delivery(): void
    {
        $this->sentOrder(5);

        $this->get(route('supplies.index'))
            ->assertOk()
            ->assertViewHas('awaitingOrdersCount', 1)
            ->assertSee('Requested Purchase Orders');
    }

    public function test_the_supplies_list_shows_the_order_a_supply_came_from(): void
    {
        $order = $this->sentOrder(5);
        $this->recordSupply($order, 5);

        // Fully received, so it is off the awaiting list but still named on the row.
        $this->get(route('supplies.index'))
            ->assertOk()
            ->assertViewHas('awaitingOrdersCount', 0)
            ->assertSee($order->code);
    }

    public function test_the_price_list_endpoint_returns_the_vendors_products_and_costs(): void
    {
        $this->get(route('purchase-orders.vendor-products', $this->vendor))
            ->assertOk()
            ->assertJsonFragment(['id' => $this->product->id, 'unit_cost' => 47.50]);
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
    public function test_the_listing_can_be_searched_by_vendor(): void
    {
        $mine = $this->createOrder();

        $other = Vendor::factory()->create(['name' => 'Northwind Traders']);
        $theirs = PurchaseOrder::create([
            'vendor_id' => $other->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $this->get(route('purchase-orders.index', ['search' => 'Northwind']))
            ->assertOk()
            ->assertSee($theirs->code)
            ->assertDontSee($mine->code);
    }

    public function test_the_listing_can_be_searched_by_the_order_code(): void
    {
        $order = $this->createOrder();

        $this->get(route('purchase-orders.index', ['search' => $order->code]))
            ->assertOk()
            ->assertSee($order->code);
    }

    public function test_the_listing_filters_by_status_and_warehouse(): void
    {
        $draft = $this->createOrder();
        $sent = $this->sentOrder();

        $this->get(route('purchase-orders.index', ['status' => PurchaseOrder::STATUS_SENT]))
            ->assertOk()
            ->assertSee($sent->code)
            ->assertDontSee($draft->code);

        $elsewhere = Warehouse::factory()->create();

        // Nothing was ordered into the other warehouse, so the listing is empty.
        $this->get(route('purchase-orders.index', ['warehouse_id' => $elsewhere->id]))
            ->assertOk()
            ->assertDontSee($draft->code)
            ->assertDontSee($sent->code);
    }

    public function test_an_unknown_sort_field_does_not_break_the_listing(): void
    {
        $order = $this->createOrder();

        // The sort comes off the query string, so it is only ever a column
        // this page offers - anything else falls back rather than erroring.
        $this->get(route('purchase-orders.index', ['sort' => 'vendor_id; drop table', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee($order->code);
    }

}
