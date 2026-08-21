<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Retailer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\Warehouse;
use App\Services\Pricing\PriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product nobody has priced cannot be ordered - either way round.
 *
 * The product form takes no prices at all: it describes the product, and
 * Catalog > Product Pricing sets what it costs. So a product exists, correctly,
 * with no purchase price and no selling price behind it - and until somebody
 * sets one there is no figure for an order to be written against.
 *
 * Both order forms post a price field, and both used to accept whatever was in
 * it. That is the gap these cover: not a wrong price, but no price at all.
 */
class UnpricedProductsCannotBeOrderedTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $lists;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = app(PriceListService::class);
    }

    /** A product as the create form leaves it: described, not priced. */
    private function unpricedProduct(): Product
    {
        return Product::factory()->create(['purchase_price' => 0, 'selling_price' => 0, 'markup' => 25]);
    }

    /** A vendor who carries the product - carriage is not a price. */
    private function vendorCarrying(Product $product): Vendor
    {
        $vendor = Vendor::factory()->create();

        VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id]);

        return $vendor;
    }

    private function purchasePayload(Vendor $vendor, Product $product, float $unitCost = 47.50): array
    {
        return [
            'vendor_id' => $vendor->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => $unitCost],
            ],
        ];
    }

    private function salePayload(Product $product, Customer $customer, float $unitPrice): array
    {
        return [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'fulfillment_location_id' => Retailer::factory()->create()->id,
                    'fulfillment_location_type' => 'retailer',
                ],
            ],
        ];
    }

    /**
     * Someone allowed to depart from an agreed price.
     *
     * The base TestCase signs in as an admin, who holds every permission -
     * including this one - so this only makes the override explicit.
     */
    private function actAsOverrider(): User
    {
        $role = Role::firstOrCreate(['name' => 'sales-lead'], ['label' => 'Sales Lead']);
        $role->permissions()->sync(
            \App\Models\Permission::where('name', 'orders.override-price')->pluck('id')->all()
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user);

        return $user;
    }

    public function test_a_purchase_order_for_an_unpriced_product_is_rejected(): void
    {
        $product = $this->unpricedProduct();
        $vendor = $this->vendorCarrying($product);

        // The cost field is hand-editable, so a figure is posted regardless.
        $this->post(route('purchase-orders.store'), $this->purchasePayload($vendor, $product))
            ->assertSessionHasErrors('products.0.product_id');

        $this->assertSame(0, PurchaseOrder::count(), 'Nothing should have been created.');
    }

    public function test_the_same_purchase_order_is_accepted_once_the_product_is_priced(): void
    {
        $product = $this->unpricedProduct();
        $vendor = $this->vendorCarrying($product);

        $this->lists->setPrice($this->lists->forVendor($vendor), $product, 47.50);

        $this->post(route('purchase-orders.store'), $this->purchasePayload($vendor, $product))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PurchaseOrder::count());
    }

    /**
     * The heart of it: the override permission buys a different price, not a
     * price out of nothing. With no agreed figure there is nothing to depart
     * from, so the posted one would be the only price the sale ever had.
     */
    public function test_a_sales_order_for_an_unpriced_product_is_rejected_even_with_the_override(): void
    {
        $this->actAsOverrider();

        $product = $this->unpricedProduct();
        $customer = Customer::factory()->create();

        $this->post(route('orders.store'), $this->salePayload($product, $customer, 85.00))
            ->assertSessionHasErrors('products.0.unit_price');

        $this->assertSame(0, Order::count(), 'Nothing should have been created.');
    }

    public function test_that_same_user_may_still_discount_a_product_that_has_a_price(): void
    {
        $this->actAsOverrider();

        $product = $this->unpricedProduct();
        $customer = Customer::factory()->create();

        $this->lists->setPrice($this->lists->defaultFor(PriceList::TYPE_SALE), $product, 100.00);

        $this->post(route('orders.store'), $this->salePayload($product, $customer, 85.00))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(85.00, Order::first()->items->first()->unit_price);
    }

    public function test_the_order_form_offers_an_unpriced_product_as_a_disabled_option(): void
    {
        $priced = Product::factory()->create(['name' => 'Priced Widget', 'available_stocks' => 5]);
        $unpriced = Product::factory()->create([
            'name' => 'Unpriced Widget',
            'available_stocks' => 5,
            'purchase_price' => 0,
            'selling_price' => 0,
        ]);

        $this->lists->setPrice($this->lists->defaultFor(PriceList::TYPE_SALE), $priced, 100.00);

        $html = $this->get(route('orders.create'))->assertOk()->getContent();

        $this->assertStringContainsString('no price set', $html);
        $this->assertMatchesRegularExpression(
            '/value="'.$unpriced->id.'"[^>]*disabled/s',
            $html,
            'The unpriced product should be listed but not selectable.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="'.$priced->id.'"[^>]*disabled/s',
            $html,
            'A priced product must stay selectable.'
        );
    }
}
