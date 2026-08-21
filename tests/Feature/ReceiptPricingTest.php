<?php

namespace Tests\Feature;

use App\Models\Grn;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\ProductCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What posting a goods receipt does to cost and to price.
 *
 * Receiving is still the only moment cost moves, but it now moves three things
 * apart from each other: the costing ledger, the vendor's own quote, and - only
 * if the product asks for it - what we charge.
 */
class ReceiptPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Receiving moves prices only under the full model. Simple mode stops
        // it deliberately - the price the user typed stands until they change
        // it - and SimplePricingTest covers that side.
        config(['pricing.simple_mode' => false]);
    }

    private function receive(Product $product, int $quantity, float $unitCost, ?Vendor $vendor = null): Supply
    {
        $supply = Supply::factory()->create([
            'vendor_id' => $vendor?->id ?? Vendor::factory(),
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

        return $supply;
    }

    public function test_a_top_up_delivery_averages_rather_than_restating_the_shelf(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
        ]);

        $this->receive($product, 50, 400.00);
        $this->receive($product, 5, 200.00);

        // The live case: 50 @ 400 then 5 @ 200 is 381.8182, not 200.
        $this->assertEqualsWithDelta(
            381.8182,
            app(ProductCostService::class)->costAt($product),
            0.0001
        );
    }

    public function test_the_derived_selling_price_follows_the_average_not_the_last_delivery(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
            'pricing_mode' => 'cost_plus_markup',
        ]);

        $this->receive($product, 50, 400.00);
        $this->receive($product, 5, 200.00);

        // 381.8182 * 1.25 = 477.27, not 200 * 1.25 = 250.
        $price = app(PriceResolver::class)->forSale($product->fresh());

        $this->assertEqualsWithDelta(477.27, $price->unitPrice, 0.01);
        $this->assertSame('price_list', $price->source, 'The receipt should have written a real list price.');
    }

    public function test_a_manually_priced_product_is_not_repriced_by_receiving_stock(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
            'pricing_mode' => 'manual',
        ]);

        $lists = app(PriceListService::class);
        $retail = $lists->defaultFor(PriceList::TYPE_SALE);
        $lists->setPrice($retail, $product, 999.00);

        $this->receive($product, 10, 400.00);

        $this->assertEquals(
            999.00,
            app(PriceResolver::class)->forSale($product->fresh())->unitPrice,
            'A manually priced product keeps the price a human set.'
        );

        // Cost still moves - only the sale price is pinned.
        $this->assertEquals(400.00, app(ProductCostService::class)->costAt($product));
    }

    public function test_receiving_records_what_that_vendor_charged(): void
    {
        $product = Product::factory()->create(['purchase_price' => 0, 'markup' => 25]);
        $acme = Vendor::factory()->create(['name' => 'Acme']);
        $globex = Vendor::factory()->create(['name' => 'Globex']);

        $this->receive($product, 10, 400.00, $acme);
        $this->receive($product, 10, 250.00, $globex);

        $resolver = app(PriceResolver::class);

        $this->assertEquals(400.00, $resolver->forPurchase($product, $acme)->unitPrice);
        $this->assertEquals(250.00, $resolver->forPurchase($product, $globex)->unitPrice);
    }

    public function test_a_vendors_new_quote_closes_the_old_one_rather_than_erasing_it(): void
    {
        $product = Product::factory()->create(['purchase_price' => 0, 'markup' => 25]);
        $vendor = Vendor::factory()->create();

        $this->receive($product, 10, 400.00, $vendor);
        $this->receive($product, 10, 300.00, $vendor);

        $history = app(PriceListService::class)->historyFor($product, PriceList::TYPE_PURCHASE);

        $this->assertCount(2, $history, 'Both quotes must remain on file.');
        $this->assertNotNull(
            $history->last()->ends_at,
            'The superseded quote should be closed, not deleted.'
        );
    }

    public function test_an_unpriced_delivery_does_not_record_a_cost_of_zero(): void
    {
        $product = Product::factory()->create(['purchase_price' => 0, 'markup' => 25]);

        $this->receive($product, 10, 0.00);

        $this->assertNull(
            app(ProductCostService::class)->costAt($product),
            'A delivery with no price on it is unpriced, not free.'
        );
    }
}
