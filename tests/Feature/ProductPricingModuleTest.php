<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Pricing\PriceContext;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\PriceResolver;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalog > Product Pricing.
 *
 * The screen is product-centric: what each vendor charges for a product, then
 * what we charge for it, split by where the order is fulfilled from. These pin
 * the rules that make the numbers on it trustworthy - a price change is dated
 * rather than overwritten, an auto-derived price is computed by the server, and
 * clearing a field means "not priced" rather than free.
 */
class ProductPricingModuleTest extends TestCase
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

    /** A product carried by one vendor at a known cost. */
    private function productWithVendor(float $cost = 400.00): array
    {
        $product = Product::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'Acme']);

        \App\Models\VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
        ]);

        $basis = $this->lists->setPrice($this->lists->forVendor($vendor), $product, $cost);

        return [$product, $vendor, $basis];
    }

    public function test_the_index_lists_products_with_their_purchase_and_sale_prices(): void
    {
        [$product, , $basis] = $this->productWithVendor(400.00);
        $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);

        $this->get(route('product-pricing.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('what a vendor charges you')
            ->assertSee('what you charge to customer')
            ->assertSee('400.00')
            ->assertSee('500.00');
    }

    public function test_the_editor_shows_the_cost_a_selling_price_would_be_set_against(): void
    {
        [$product, $vendor] = $this->productWithVendor(400.00);

        $this->get(route('product-pricing.edit', $product->id))
            ->assertOk()
            ->assertSee($vendor->name)
            ->assertSee('400.00');
    }

    public function test_a_vendors_cost_can_be_set_from_the_editor(): void
    {
        [$product, $vendor] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.purchase.update', $product->id), [
            'vendors' => [$vendor->id => ['unit_cost' => '425.00']],
        ])->assertRedirect();

        $this->assertEquals(
            425.00,
            app(PriceResolver::class)->forPurchase($product, $vendor)->unitPrice
        );
    }

    public function test_clearing_a_vendors_cost_means_unpriced_rather_than_free(): void
    {
        [$product, $vendor] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.purchase.update', $product->id), [
            'vendors' => [$vendor->id => ['unit_cost' => '']],
        ])->assertRedirect();

        $this->assertNull(
            app(PriceResolver::class)->forPurchase($product, $vendor),
            'A cleared cost must not read as zero on a purchase order.'
        );
    }

    public function test_a_vendor_who_does_not_carry_the_product_cannot_be_priced_for_it(): void
    {
        [$product] = $this->productWithVendor(400.00);
        $stranger = Vendor::factory()->create();

        $this->put(route('product-pricing.purchase.update', $product->id), [
            'vendors' => [$stranger->id => ['unit_cost' => '1.00']],
        ])->assertRedirect();

        $this->assertNull(app(PriceResolver::class)->forPurchase($product, $stranger));
    }

    public function test_an_auto_derived_selling_price_is_computed_from_the_basis_and_markup(): void
    {
        [$product, , $basis] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => [
                'warehouse' => [
                    'enabled' => '1',
                    'basis_price_list_item_id' => $basis->id,
                    'markup_percent' => '25',
                    'is_auto_derived' => '1',
                    // A figure the browser might have sent. The server must
                    // work the price out itself rather than trust it.
                    'unit_price' => '1.00',
                ],
            ],
        ])->assertRedirect();

        $row = PriceListItem::where('product_id', $product->id)
            ->where('price_list_id', $this->pricing->saleListFor('warehouse')->id)
            ->whereNull('ends_at')->first();

        $this->assertEquals(500.00, $row->unit_price, '400 + 25% is 500, not what the form posted.');
        $this->assertEquals(100.00, $row->grossProfit());
    }

    public function test_unticking_auto_lets_a_price_be_typed_by_hand(): void
    {
        [$product, , $basis] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => [
                'warehouse' => [
                    'enabled' => '1',
                    'basis_price_list_item_id' => $basis->id,
                    'markup_percent' => '25',
                    'is_auto_derived' => '0',
                    'unit_price' => '619.99',
                ],
            ],
        ])->assertRedirect();

        $row = PriceListItem::where('product_id', $product->id)
            ->where('price_list_id', $this->pricing->saleListFor('warehouse')->id)
            ->whereNull('ends_at')->first();

        $this->assertEquals(619.99, $row->unit_price);
        $this->assertEquals(219.99, $row->grossProfit(), 'Margin is still measured against the basis.');
    }

    public function test_warehouse_and_retailer_fulfilment_can_be_priced_differently(): void
    {
        [$product, , $basis] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => [
                'warehouse' => [
                    'enabled' => '1', 'basis_price_list_item_id' => $basis->id,
                    'markup_percent' => '25', 'is_auto_derived' => '1',
                ],
                'retailer' => [
                    'enabled' => '1', 'basis_price_list_item_id' => $basis->id,
                    'markup_percent' => '40', 'is_auto_derived' => '1',
                ],
            ],
        ])->assertRedirect();

        $resolver = app(PriceResolver::class);

        $this->assertEquals(500.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Warehouse::factory()->create(),
        ))->unitPrice);

        $this->assertEquals(560.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Retailer::factory()->create(),
        ))->unitPrice);
    }

    public function test_a_price_applies_to_any_location_of_that_kind(): void
    {
        [$product, , $basis] = $this->productWithVendor(400.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['retailer' => [
                'enabled' => '1', 'basis_price_list_item_id' => $basis->id,
                'markup_percent' => '40', 'is_auto_derived' => '1',
            ]],
        ]);

        // A store opened after the price was set still gets it - the assignment
        // is to the kind of location, not to one named store.
        $newStore = Retailer::factory()->create();

        $this->assertEquals(560.00, app(PriceResolver::class)->forSale(
            $product, new PriceContext(fulfilmentLocation: $newStore)
        )->unitPrice);
    }

    public function test_turning_a_row_off_stops_the_price_without_losing_it(): void
    {
        [$product] = $this->productWithVendor(400.00);
        $row = $this->lists->setPrice($this->pricing->saleListFor('warehouse'), $product, 500.00);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => ['enabled' => '0']],
        ])->assertRedirect();

        $this->assertNotNull($row->refresh()->ends_at, 'Closed, not deleted.');
        $this->assertEquals(500.00, $row->unit_price, 'And still readable at its own figure.');
    }

    public function test_changing_a_price_leaves_the_previous_one_readable_at_its_own_date(): void
    {
        [$product] = $this->productWithVendor(400.00);
        $list = $this->pricing->saleListFor('warehouse');
        $original = $this->lists->setPrice($list, $product, 500.00, 1, now()->subMonth());

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'is_auto_derived' => '0', 'unit_price' => '540.00',
            ]],
        ])->assertRedirect();

        $this->assertNotNull($original->refresh()->ends_at);
        $this->assertEquals(500.00, $original->unit_price);
    }

    public function test_the_history_page_shows_what_a_product_has_been_priced_at(): void
    {
        [$product] = $this->productWithVendor(400.00);
        $list = $this->pricing->saleListFor('warehouse');
        $this->lists->setPrice($list, $product, 500.00, 1, now()->subMonths(3));
        $this->lists->setPrice($list, $product, 540.00);

        $this->get(route('product-pricing.history', $product->id))
            ->assertOk()
            ->assertSee('500.00')
            ->assertSee('540.00');
    }

    public function test_the_module_is_gated_by_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'warehouse-staff'], ['label' => 'Warehouse']);
        $role->permissions()->sync(Permission::where('name', 'products.view')->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user)->get(route('product-pricing.index'))->assertForbidden();
    }

    public function test_viewing_does_not_confer_managing(): void
    {
        [$product, $vendor] = $this->productWithVendor(400.00);

        $role = Role::firstOrCreate(['name' => 'viewer'], ['label' => 'Viewer']);
        $role->permissions()->sync(Permission::where('name', 'product-pricing.view')->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user)->get(route('product-pricing.index'))->assertOk();

        $this->actingAs($user)
            ->put(route('product-pricing.purchase.update', $product->id), [
                'vendors' => [$vendor->id => ['unit_cost' => '1.00']],
            ])
            ->assertForbidden();
    }
}
