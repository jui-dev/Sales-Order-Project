<?php

namespace Tests\Feature;

use App\Models\Grn;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\SupplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pricing changes at exactly one moment: when goods are received.
 *
 * The system used to reprice a product the instant a supply line was created -
 * so merely ordering at a new cost rewrote the selling price of a product that
 * had not arrived, and might never arrive. These pin the corrected boundary.
 */
class ProductPricingLifecycleTest extends TestCase
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

    private function supplyFor(Product $product, float $unitCost, int $quantity = 10): Supply
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

        return $supply->fresh('items');
    }

    public function test_recording_a_supply_does_not_reprice_the_product(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
        ]);

        $this->supplyFor($product, 50.00);

        $product->refresh();
        $this->assertEquals(0, $product->purchase_price, 'Ordering must not set a cost.');
        $this->assertEquals(0, $product->selling_price, 'Ordering must not set a price.');
    }

    public function test_posting_a_grn_sets_the_cost_and_derives_the_selling_price(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 25,
        ]);

        $supply = $this->supplyFor($product, 47.50);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'draft']);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $product->refresh();
        $this->assertEquals(47.50, $product->purchase_price);
        // 47.50 + 25% = 59.375, rounded to 59.38
        $this->assertEquals(59.38, $product->selling_price);
        $this->assertEquals(11.88, $product->gross_profit);
    }

    public function test_the_products_own_markup_drives_the_derived_price(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 0,
            'markup' => 50,
        ]);

        $supply = $this->supplyFor($product, 100.00);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'draft']);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $this->assertEquals(150.00, $product->refresh()->selling_price);
    }

    public function test_a_never_received_product_is_not_priced_at_zero(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 80.00,
            'markup' => 25,
        ]);

        // A plain edit must not derive 0 * 1.25 = 0 over the stored price.
        $product->update(['name' => 'Renamed']);

        $this->assertEquals(80.00, $product->refresh()->selling_price);
    }

    public function test_auto_pricing_can_be_turned_off_per_product(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price' => 80.00,
            'markup' => 25,
            'auto_pricing_enabled' => false,
        ]);

        $supply = $this->supplyFor($product, 20.00);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'draft']);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        $product->refresh();
        $this->assertEquals(20.00, $product->purchase_price, 'Cost is still recorded.');
        $this->assertEquals(80.00, $product->selling_price, 'But the manual price stands.');
    }

    public function test_creating_a_product_through_the_form_stores_no_selling_price(): void
    {
        $vendor = Vendor::factory()->create();

        $this->post(route('products.store'), [
            'name' => 'Widget',
            'sku' => 'SKU-WIDGET',
            'markup' => 30,
            'vendor_ids' => [$vendor->id],
            // A tampered post trying to set the price directly must be ignored.
            'selling_price' => 999.00,
        ])->assertRedirect();

        $product = Product::where('sku', 'SKU-WIDGET')->firstOrFail();
        $this->assertEquals(0, $product->selling_price);
        $this->assertEquals(30, $product->markup);
        $this->assertTrue($product->vendors->contains($vendor));
    }

    /**
     * A product form describes the product, not what it is worth.
     *
     * Price, cost and markup all moved to Catalog > Product Pricing: cost
     * differs per vendor, price differs by where the order is fulfilled from,
     * and both change over time. Leaving a second editable copy here is how
     * two figures for one product drifted apart in the first place.
     */
    public function test_the_product_forms_carry_no_pricing_fields(): void
    {
        Vendor::factory()->create(['name' => 'Acme']);
        $product = Product::factory()->create();

        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Acme')
            ->assertDontSee('name="selling_price"', false)
            ->assertDontSee('name="markup"', false);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertDontSee('name="selling_price"', false)
            ->assertDontSee('name="markup"', false);
    }

    public function test_editing_a_product_keeps_its_vendor_costs_intact(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create();
        $product->vendors()->attach($vendor->id, ['unit_cost' => 42.00]);

        $this->put(route('products.update', $product), [
            'name' => 'Renamed',
            'sku' => $product->sku,
            'markup' => 25,
            'vendor_ids' => [$vendor->id],
        ])->assertRedirect();

        $this->assertEquals(
            42.00,
            $product->fresh()->vendors()->first()->pivot->unit_cost,
            'Re-saving the product must not wipe an agreed cost.'
        );
    }
}
