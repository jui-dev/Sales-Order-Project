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

        // Assigning records carriage only; the cost is set under Product
        // Pricing, so that there is exactly one place a price is written.
        $this->post(route('vendors.products.assign', $acme), [
            'product_id' => $product->id,
        ])->assertRedirect(route('vendors.show', $acme->id));

        $this->post(route('vendors.products.assign', $globex), [
            'product_id' => $product->id,
        ])->assertRedirect(route('vendors.show', $globex->id));

        $lists = app(\App\Services\Pricing\PriceListService::class);
        $lists->setPrice($lists->forVendor($acme), $product, 50.00);
        $lists->setPrice($lists->forVendor($globex), $product, 47.50);

        // Resolved from each vendor's own purchase price list, which is where
        // the cost now lives - the pivot only records that they carry it.
        $resolver = app(\App\Services\Pricing\PriceResolver::class);

        $this->assertEquals(50.00, $resolver->forPurchase($product, $acme)->unitPrice);
        $this->assertEquals(47.50, $resolver->forPurchase($product, $globex)->unitPrice);
    }

    public function test_a_product_can_be_assigned_before_a_price_is_agreed(): void
    {
        $product = Product::factory()->create();
        $vendor  = Vendor::factory()->create();

        $this->post(route('vendors.products.assign', $vendor), [
            'product_id' => $product->id,
        ])->assertRedirect();

        // Carried, but with no price row - "no price agreed" must stay
        // distinguishable from free, which a zero would not be.
        $this->assertSame(1, VendorProduct::count());
        $this->assertNull(
            app(\App\Services\Pricing\PriceResolver::class)->forPurchase($product, $vendor)
        );
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

    /**
     * The vendor page shows what they charge but no longer sets it.
     *
     * Cost is decided under Catalog > Product Pricing. Two editable copies of
     * one price is exactly how the figures in this system drifted apart, so
     * this screen keeps only what it owns: which products the vendor carries,
     * their vendor SKU, and whether the row is active.
     */
    public function test_the_vendor_page_saves_carriage_but_ignores_a_posted_cost(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create();
        $row = VendorProduct::create([
            'vendor_id'  => $vendor->id,
            'product_id' => $product->id,
        ]);

        $lists = app(\App\Services\Pricing\PriceListService::class);
        $lists->setPrice($lists->forVendor($vendor), $product, 10.00);

        $this->put(route('vendors.price-list.update', $vendor), [
            'rows' => [
                $row->id => ['unit_cost' => '12.75', 'vendor_sku' => 'V-123', 'is_active' => '1'],
            ],
        ])->assertRedirect(route('vendors.show', $vendor->id));

        $this->assertSame('V-123', $row->refresh()->vendor_sku, 'Carriage details are still saved here.');

        $this->assertEquals(
            10.00,
            app(\App\Services\Pricing\PriceResolver::class)->forPurchase($product, $vendor)->unitPrice,
            'A cost posted to this screen must be ignored - pricing lives in one place.'
        );
    }

    public function test_the_price_list_cannot_reprice_another_vendors_row(): void
    {
        $mine     = Vendor::factory()->create();
        $theirs   = Vendor::factory()->create();
        $product  = Product::factory()->create();
        $theirRow = VendorProduct::create([
            'vendor_id'  => $theirs->id,
            'product_id' => $product->id,
        ]);

        $lists = app(\App\Services\Pricing\PriceListService::class);
        $lists->setPrice($lists->forVendor($theirs), $product, 10.00);

        // A tampered form posting someone else's row id must not take effect.
        $this->put(route('vendors.price-list.update', $mine), [
            'rows' => [$theirRow->id => ['unit_cost' => '0.01']],
        ]);

        $this->assertEquals(
            10.00,
            app(\App\Services\Pricing\PriceResolver::class)->forPurchase($product, $theirs)->unitPrice,
        );
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
        ]);

        $this->get(route('vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Products Supplied')
            ->assertSee('Widget');
    }
}
