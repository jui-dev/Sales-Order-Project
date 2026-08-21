<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Retailer;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\Warehouse;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\PriceResolver;
use App\Services\ProductPricingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A price that has been charged on a real document cannot be altered.
 *
 * Superseding it stays possible - that is how prices are meant to change - but
 * the figure a purchase order or a sales order was actually placed at is a
 * matter of record. The guard sits on the model rather than in a controller, so
 * it holds whatever reaches for the row.
 */
class UsedPriceIsLockedTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $lists;
    private ProductPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = app(PriceListService::class);
        $this->pricing = app(ProductPricingService::class);
    }

    private function productWithVendor(float $cost = 400.00): array
    {
        $product = Product::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'Acme']);
        VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id]);
        $quote = $this->lists->setPrice($this->lists->forVendor($vendor), $product, $cost);

        return [$product, $vendor, $quote];
    }

    private function sellAt(Product $product, PriceListItem $row, float $price): Order
    {
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'pending',
            'order_date' => now(),
            'total_amount' => $price,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $price,
            'unit_cost' => 0,
            'price_list_item_id' => $row->id,
            'subtotal' => $price,
        ]);

        return $order;
    }

    public function test_an_unused_price_is_not_locked(): void
    {
        [$product] = $this->productWithVendor();
        $row = $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);

        $this->assertFalse($row->isInUse());
        $this->assertNull($row->usageSummary());
    }

    public function test_a_price_charged_on_a_sales_order_is_locked(): void
    {
        [$product] = $this->productWithVendor();
        $row = $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);
        $this->sellAt($product, $row, 500.00);

        $this->assertTrue($row->refresh()->isInUse());
        $this->assertSame('1 sales order', $row->usageSummary());
    }

    public function test_a_locked_price_cannot_be_altered(): void
    {
        [$product] = $this->productWithVendor();
        $row = $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);
        $this->sellAt($product, $row, 500.00);

        $this->expectException(DomainException::class);

        $row->refresh()->update(['unit_price' => 1.00]);
    }

    public function test_a_locked_price_can_still_be_closed_so_a_new_one_supersedes_it(): void
    {
        [$product] = $this->productWithVendor();
        $list = $this->pricing->saleListFor('warehouse');
        $row = $this->lists->setPrice($list, $product, 500.00, 1, now()->subMonth());
        $this->sellAt($product, $row, 500.00);

        // Superseding must keep working - closing a row records that it stopped
        // applying, which is not the same as changing what it charged.
        $this->lists->setPrice($list, $product, 560.00);

        $row->refresh();
        $this->assertNotNull($row->ends_at, 'The charged price should be closed...');
        $this->assertEquals(500.00, $row->unit_price, '...but still read at what was charged.');

        $this->assertEquals(560.00, app(PriceResolver::class)->forSale($product)->unitPrice);
    }

    public function test_the_editor_shows_a_used_sale_price_as_locked(): void
    {
        [$product] = $this->productWithVendor();
        $row = $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);
        $this->sellAt($product, $row, 500.00);

        $this->get(route('product-pricing.edit', $product->id))
            ->assertOk()
            ->assertSee('In use')
            ->assertSee('Charged on 1 sales order')
            ->assertSee('Change price');
    }

    public function test_a_quote_used_on_a_purchase_order_is_locked(): void
    {
        [$product, $vendor, $quote] = $this->productWithVendor(400.00);

        $order = PurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'status' => 'draft',
            'total_cost' => 400.00,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_ordered' => 1,
            'quantity_received' => 0,
            'unit_cost' => 400.00,
            'price_list_item_id' => $quote->id,
            'subtotal' => 400.00,
        ]);

        $quote->refresh();
        $this->assertTrue($quote->isInUse());
        $this->assertSame('1 purchase order', $quote->usageSummary());

        $this->expectException(DomainException::class);
        $quote->update(['unit_price' => 1.00]);
    }

    public function test_raising_a_purchase_order_records_the_quote_it_used(): void
    {
        [$product, $vendor, $quote] = $this->productWithVendor(400.00);

        $this->post(route('purchase-orders.store'), [
            'vendor_id' => $vendor->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 400.00],
            ],
        ])->assertRedirect();

        $this->assertSame(
            $quote->id,
            PurchaseOrderItem::first()->price_list_item_id,
            'The line should say which quote it was priced from.'
        );
        $this->assertTrue($quote->refresh()->isInUse());
    }

    public function test_a_hand_edited_cost_does_not_lock_the_quote_it_departed_from(): void
    {
        [$product, $vendor, $quote] = $this->productWithVendor(400.00);

        // Ordered at something other than the quoted figure, so the order did
        // not actually use that price row.
        $this->post(route('purchase-orders.store'), [
            'vendor_id' => $vendor->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 385.00],
            ],
        ])->assertRedirect();

        $this->assertNull(PurchaseOrderItem::first()->price_list_item_id);
        $this->assertFalse($quote->refresh()->isInUse());
    }

    public function test_setting_a_new_price_over_a_locked_one_is_accepted_by_the_editor(): void
    {
        [$product] = $this->productWithVendor();
        $list = $this->pricing->saleListFor('warehouse');
        $row = $this->lists->setPrice($list, $product, 500.00, 1, now()->subMonth());
        $this->sellAt($product, $row, 500.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'charged_basis' => 'none',
                'lines' => ['none' => ['is_auto_derived' => '0', 'unit_price' => '575.00']],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(500.00, $row->refresh()->unit_price, 'What was charged is untouched.');
        $this->assertEquals(575.00, app(PriceResolver::class)->forSale($product)->unitPrice);
    }
}
