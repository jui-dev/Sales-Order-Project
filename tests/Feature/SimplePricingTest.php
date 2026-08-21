<?php

namespace Tests\Feature;

use App\Models\Grn;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\Retailer;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\Pricing\PriceContext;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\ProductCostService;
use App\Services\Pricing\SimplePricingService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pricing with the vendor dimension collapsed.
 *
 * One product carries one purchase price whoever supplies it, marked up by a
 * fixed percentage to give one selling price and one gross profit. The point of
 * these is that the simplification is a matter of what gets written, not of
 * where prices live: the same lists, the same effective dating, the same
 * resolver. Turning the flag off has to hand the full model back intact, which
 * is only true while nothing here writes prices its own way.
 */
class SimplePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pricing.simple_mode' => true, 'pricing.default_markup' => 25]);
    }

    /** A product two different vendors can supply. */
    private function productWithVendors(int $count = 2): array
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
        ]);

        $vendors = [];

        for ($i = 0; $i < $count; $i++) {
            $vendor = Vendor::factory()->create();
            VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id]);
            $vendors[] = $vendor;
        }

        return [$product->refresh(), $vendors];
    }

    private function setPrice(Product $product, ?string $cost): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('product-pricing.update', $product->id), ['unit_cost' => $cost]);
    }

    /* ---------------------------------------------------------------------
     | One cost
     |---------------------------------------------------------------------*/

    public function test_one_cost_is_what_every_vendor_charges(): void
    {
        [$product, $vendors] = $this->productWithVendors(3);

        $this->setPrice($product, '100.00')->assertRedirect();

        $resolver = app(PriceResolver::class);

        foreach ($vendors as $vendor) {
            $quote = $resolver->forPurchase($product, $vendor);

            $this->assertNotNull($quote, "Vendor {$vendor->id} should be quoted.");
            $this->assertEquals(100.00, $quote->unitPrice);
        }
    }

    public function test_a_vendor_added_after_pricing_is_quoted_the_same(): void
    {
        [$product] = $this->productWithVendors(1);

        $this->setPrice($product, '100.00')->assertRedirect();

        $latecomer = Vendor::factory()->create();

        $this->post(route('products.vendors.add', $product->id), ['vendor_id' => $latecomer->id])
            ->assertRedirect();

        $quote = app(PriceResolver::class)->forPurchase($product->fresh(), $latecomer);

        $this->assertNotNull($quote, 'A vendor added after the price was set should still be quoted.');
        $this->assertEquals(100.00, $quote->unitPrice);
    }

    /* ---------------------------------------------------------------------
     | One price
     |---------------------------------------------------------------------*/

    public function test_the_selling_price_is_the_cost_plus_the_fixed_markup(): void
    {
        [$product] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();

        $resolver = app(PriceResolver::class);
        $warehouse = Warehouse::factory()->create();
        $retailer = Retailer::factory()->create();

        // The same figure wherever the order is fulfilled from.
        foreach ([$warehouse, $retailer] as $location) {
            $price = $resolver->forSale($product->fresh(), new PriceContext(fulfilmentLocation: $location));

            $this->assertNotNull($price);
            $this->assertEquals(125.00, $price->unitPrice);
            $this->assertSame('price_list', $price->source, 'It should be a real list price, not a fallback.');
        }
    }

    public function test_each_fulfilment_kind_carries_exactly_one_charged_price(): void
    {
        [$product] = $this->productWithVendors();
        $pricing = app(ProductPricingService::class);

        $this->setPrice($product, '100.00')->assertRedirect();
        // Saved twice at different figures: the second must supersede the
        // first, not stand beside it.
        $this->setPrice($product, '200.00')->assertRedirect();

        foreach (array_keys(ProductPricingService::FULFILMENT_KINDS) as $kind) {
            $standing = PriceListItem::where('price_list_id', $pricing->saleListFor($kind)->id)
                ->where('product_id', $product->id)
                ->whereNull('ends_at')
                ->get();

            $this->assertCount(1, $standing, "The {$kind} list should hold one standing price.");
            $this->assertTrue((bool) $standing->first()->is_charged);
            $this->assertEquals(250.00, $standing->first()->unit_price);
        }
    }

    public function test_the_legacy_columns_follow_so_the_rest_of_the_app_agrees(): void
    {
        [$product] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();

        $product->refresh();

        $this->assertEquals(100.00, $product->purchase_price);
        $this->assertEquals(125.00, $product->selling_price);
        $this->assertEquals(25.00, $product->gross_profit);
        $this->assertEquals(25.00, $product->markup);
    }

    public function test_a_product_markup_of_its_own_is_overridden_by_the_fixed_one(): void
    {
        [$product] = $this->productWithVendors();
        $product->forceFill(['markup' => 60])->save();

        $this->setPrice($product, '100.00')->assertRedirect();

        // 125, not 160 - simple mode prices the whole catalogue at one figure.
        $this->assertEquals(125.00, app(PriceResolver::class)->forSale($product->fresh())->unitPrice);
    }

    /* ---------------------------------------------------------------------
     | What the screens show
     |---------------------------------------------------------------------*/

    public function test_the_snapshot_reports_cost_price_and_gross_profit(): void
    {
        [$product] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();

        $snapshot = app(SimplePricingService::class)->snapshotFor($product->fresh());

        $this->assertEquals(100.00, $snapshot['cost']);
        $this->assertEquals(25.0, $snapshot['markup']);
        $this->assertEquals(125.00, $snapshot['selling']);
        $this->assertEquals(25.00, $snapshot['gross_profit']);
        $this->assertTrue($snapshot['in_step']);
    }

    public function test_an_unpriced_product_reports_no_figures_rather_than_zeroes(): void
    {
        [$product] = $this->productWithVendors();

        $snapshot = app(SimplePricingService::class)->snapshotFor($product);

        $this->assertNull($snapshot['cost']);
        $this->assertNull($snapshot['selling']);
        $this->assertNull($snapshot['gross_profit']);
    }

    public function test_the_index_shows_the_price_it_saved(): void
    {
        [$product] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();

        $this->get(route('product-pricing.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('100.00')
            ->assertSee('125.00')
            ->assertSee('25.00');
    }

    /* ---------------------------------------------------------------------
     | Clearing
     |---------------------------------------------------------------------*/

    public function test_clearing_the_box_unprices_rather_than_zeroes(): void
    {
        [$product, $vendors] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();
        $this->setPrice($product, null)->assertRedirect();

        $resolver = app(PriceResolver::class);

        $this->assertNull(
            $resolver->forPurchase($product->fresh(), $vendors[0]),
            'No price agreed must not read as a cost of zero.'
        );

        $this->assertNull(
            $resolver->forSale($product->fresh()),
            'With no cost left there is nothing to fall back to either.'
        );
    }

    public function test_saving_the_same_price_again_does_not_churn_the_history(): void
    {
        [$product] = $this->productWithVendors();

        $this->setPrice($product, '100.00')->assertRedirect();
        $before = PriceListItem::where('product_id', $product->id)->count();

        $this->setPrice($product, '100.00')->assertRedirect();

        $this->assertSame(
            $before,
            PriceListItem::where('product_id', $product->id)->count(),
            'Setting the price it already is should leave the rows alone.'
        );
    }

    /* ---------------------------------------------------------------------
     | Receiving goods
     |---------------------------------------------------------------------*/

    public function test_receiving_goods_records_the_cost_but_moves_no_price(): void
    {
        [$product, $vendors] = $this->productWithVendors(1);
        $vendor = $vendors[0];

        $this->setPrice($product, '100.00')->assertRedirect();

        $supply = Supply::factory()->create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => Warehouse::factory(),
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 120.00,
            'subtotal' => 1200.00,
        ]);

        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $product->refresh();
        $resolver = app(PriceResolver::class);

        // What the delivery cost is still a fact worth recording.
        $this->assertEquals(120.00, app(ProductCostService::class)->costAt($product));
        $this->assertSame(1, ProductCost::where('product_id', $product->id)->count());

        // But nothing the user set has moved.
        $this->assertEquals(100.00, $product->purchase_price);
        $this->assertEquals(125.00, $product->selling_price);
        $this->assertEquals(100.00, $resolver->forPurchase($product, $vendor)->unitPrice);
        $this->assertEquals(125.00, $resolver->forSale($product)->unitPrice);
    }

    /* ---------------------------------------------------------------------
     | Reversibility
     |---------------------------------------------------------------------*/

    public function test_the_full_editor_comes_back_with_the_prices_intact(): void
    {
        [$product, $vendors] = $this->productWithVendors(2);

        $this->setPrice($product, '100.00')->assertRedirect();

        config(['pricing.simple_mode' => false]);

        // The per-vendor editor reads the same rows and finds both vendors
        // costed, because that is where simple mode wrote them.
        $data = app(ProductPricingService::class)->editorData($product->fresh());

        $this->assertCount(2, $data['vendors']);

        foreach ($data['vendors'] as $vendor) {
            $this->assertEquals(100.00, $vendor->current_cost);
        }

        $this->assertEquals(100.00, $data['averageCost']);

        $this->get(route('product-pricing.edit', $product->id))
            ->assertOk()
            ->assertSee($vendors[0]->name)
            ->assertSee('100.00');
    }
}
