<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purchase cost belongs to the vendor/product pair, not to the product.
 *
 * These cover the half that makes a purchase order able to price itself: the
 * same product costing different amounts from different vendors, and an
 * assigned-but-unpriced row staying null rather than reading as free.
 */
class VendorPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_product_can_carry_a_different_cost_per_vendor(): void
    {
        $product = Product::factory()->create();
        $acme    = Vendor::factory()->create(['name' => 'Acme']);
        $globex  = Vendor::factory()->create(['name' => 'Globex']);

        $this->post(route('vendors.products.assign', $acme), [
            'product_id' => $product->id,
            'unit_cost'  => 50.00,
        ])->assertRedirect(route('vendors.show', $acme->id));

        $this->post(route('vendors.products.assign', $globex), [
            'product_id' => $product->id,
            'unit_cost'  => 47.50,
        ])->assertRedirect(route('vendors.show', $globex->id));

        // Compared numerically: the pivot does not carry VendorProduct's decimal cast.
        $this->assertEquals(50.00, $acme->products()->first()->pivot->unit_cost);
        $this->assertEquals(47.50, $globex->products()->first()->pivot->unit_cost);
    }

    public function test_a_product_can_be_assigned_before_a_price_is_agreed(): void
    {
        $product = Product::factory()->create();
        $vendor  = Vendor::factory()->create();

        $this->post(route('vendors.products.assign', $vendor), [
            'product_id' => $product->id,
        ])->assertRedirect();

        // Null, not 0.00 - "no price agreed" must stay distinguishable from free.
        $this->assertNull(VendorProduct::first()->unit_cost);
    }

    public function test_a_product_cannot_be_assigned_to_the_same_vendor_twice(): void
    {
        $product = Product::factory()->create();
        $vendor  = Vendor::factory()->create();

        $this->post(route('vendors.products.assign', $vendor), ['product_id' => $product->id]);

        $this->post(route('vendors.products.assign', $vendor), ['product_id' => $product->id])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(1, VendorProduct::count());
    }

    public function test_prices_can_be_edited_from_the_price_list(): void
    {
        $vendor = Vendor::factory()->create();
        $row = VendorProduct::create([
            'vendor_id'  => $vendor->id,
            'product_id' => Product::factory()->create()->id,
            'unit_cost'  => 10.00,
        ]);

        $this->put(route('vendors.price-list.update', $vendor), [
            'rows' => [
                $row->id => ['unit_cost' => '12.75', 'vendor_sku' => 'V-123', 'is_active' => '1'],
            ],
        ])->assertRedirect(route('vendors.show', $vendor->id));

        $row->refresh();
        $this->assertSame('12.75', $row->unit_cost);
        $this->assertSame('V-123', $row->vendor_sku);
    }

    public function test_the_price_list_cannot_reprice_another_vendors_row(): void
    {
        $mine     = Vendor::factory()->create();
        $theirs   = Vendor::factory()->create();
        $theirRow = VendorProduct::create([
            'vendor_id'  => $theirs->id,
            'product_id' => Product::factory()->create()->id,
            'unit_cost'  => 10.00,
        ]);

        // A tampered form posting someone else's row id must not take effect.
        $this->put(route('vendors.price-list.update', $mine), [
            'rows' => [$theirRow->id => ['unit_cost' => '0.01']],
        ]);

        $this->assertSame('10.00', $theirRow->fresh()->unit_cost);
    }

    public function test_a_product_can_be_removed_from_the_price_list(): void
    {
        $vendor = Vendor::factory()->create();
        $row = VendorProduct::create([
            'vendor_id'  => $vendor->id,
            'product_id' => Product::factory()->create()->id,
        ]);

        $this->delete(route('vendors.products.remove', [$vendor, $row->id]))
            ->assertRedirect(route('vendors.show', $vendor->id));

        $this->assertSame(0, VendorProduct::count());
    }

    public function test_the_vendor_page_renders_its_price_list(): void
    {
        $vendor  = Vendor::factory()->create();
        $product = Product::factory()->create(['name' => 'Widget']);
        VendorProduct::create([
            'vendor_id'  => $vendor->id,
            'product_id' => $product->id,
            'unit_cost'  => 50.00,
        ]);

        $this->get(route('vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Price List')
            ->assertSee('Widget');
    }
}
