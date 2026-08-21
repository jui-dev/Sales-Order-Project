<?php

namespace Tests\Feature;

use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\Warehouse;
use App\Services\Pricing\PriceContext;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\PriceResolver;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two short ways through the pricing editor.
 *
 * Pricing a product against every vendor cost, for every fulfilment kind, is
 * the full picture and sometimes more than is wanted. These cover the
 * shortcuts: one averaged price instead of one per vendor, and the same price
 * everywhere instead of one per location kind.
 *
 * Both decide what gets written. Neither changes how prices are stored, which
 * is what these pin down - a shortcut that quietly introduced a second place a
 * price lives would undo the point of the ledger.
 */
class ProductPricingShortcutsTest extends TestCase
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

    /**
     * A product carried by several vendors, each at its own cost.
     *
     * @param  array<int, float>  $costs
     * @return array{0: Product, 1: array<int, int>}  the product and its basis ids
     */
    private function productCarriedAt(array $costs): array
    {
        $product = Product::factory()->create();
        $bases = [];

        foreach ($costs as $cost) {
            $vendor = Vendor::factory()->create();
            VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id]);
            $bases[] = $this->lists->setPrice($this->lists->forVendor($vendor), $product, $cost)->id;
        }

        return [$product, $bases];
    }

    /** The standing sale rows for one fulfilment kind. */
    private function standingRows(Product $product, string $kind)
    {
        return PriceListItem::where('product_id', $product->id)
            ->where('price_list_id', $this->pricing->saleListFor($kind)->id)
            ->whereNull('ends_at')
            ->get();
    }

    /* ---------------------------------------------------------------------
     | One averaged price
     |---------------------------------------------------------------------*/

    public function test_the_average_cost_is_the_mean_of_what_the_vendors_charge(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00, 300.00]);

        $this->assertEquals(300.00, $this->pricing->averageCost($product));
    }

    public function test_a_vendor_with_no_agreed_cost_is_left_out_rather_than_counted_as_zero(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        // Carries the product, no price agreed. Averaging it in as zero would
        // put the mean at 200 - a figure nobody quoted.
        $unpriced = Vendor::factory()->create();
        VendorProduct::create(['vendor_id' => $unpriced->id, 'product_id' => $product->id]);

        $this->assertEquals(300.00, $this->pricing->averageCost($product));
    }

    public function test_a_product_nobody_has_costed_has_no_average(): void
    {
        $product = Product::factory()->create();

        $this->assertNull(
            $this->pricing->averageCost($product),
            'There is no average of no costs, and a zero would be priced on.'
        );
    }

    public function test_an_averaged_selling_price_is_the_mean_cost_plus_the_markup(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ])->assertRedirect();

        $rows = $this->standingRows($product, 'warehouse');

        $this->assertCount(1, $rows, 'One averaged price means one price.');
        $this->assertEquals(375.00, $rows->first()->unit_price, 'The mean of 200 and 400 is 300, plus 25%.');
        $this->assertTrue($rows->first()->is_charged);
        $this->assertEquals(375.00, app(PriceResolver::class)->forSale($product)->unitPrice);
    }

    public function test_the_averaged_price_is_computed_by_the_server(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'mode' => 'average',
                // A figure a tampered form might send instead.
                'average' => ['markup_percent' => '25', 'unit_price' => '1.00'],
            ]],
        ])->assertRedirect();

        $this->assertEquals(375.00, $this->standingRows($product, 'warehouse')->first()->unit_price);
    }

    public function test_switching_to_an_average_retires_the_per_vendor_prices(): void
    {
        [$product, $bases] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'charged_basis' => (string) $bases[1],
                'lines' => [
                    $bases[0] => ['markup_percent' => '25', 'is_auto_derived' => '1'],
                    $bases[1] => ['markup_percent' => '25', 'is_auto_derived' => '1'],
                ],
            ]],
        ]);

        $this->assertCount(2, $this->standingRows($product, 'warehouse'));

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ])->assertRedirect();

        $rows = $this->standingRows($product, 'warehouse');
        $this->assertCount(1, $rows, 'Two prices must not stand beside the averaged one.');
        $this->assertEquals(375.00, $rows->first()->unit_price);

        // Retired, not deleted - still readable at what they charged.
        $superseded = PriceListItem::whereIn('basis_price_list_item_id', $bases)->get();
        $this->assertCount(2, $superseded);
        $superseded->each(fn ($row) => $this->assertNotNull($row->ends_at));
    }

    public function test_switching_back_to_per_vendor_retires_the_averaged_price(): void
    {
        [$product, $bases] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'mode' => 'vendor',
                'charged_basis' => (string) $bases[0],
                'lines' => [
                    $bases[0] => ['markup_percent' => '25', 'is_auto_derived' => '1'],
                    $bases[1] => ['markup_percent' => '25', 'is_auto_derived' => '1'],
                ],
            ]],
        ])->assertRedirect();

        $rows = $this->standingRows($product, 'warehouse');
        $this->assertCount(2, $rows, 'The averaged figure must not stand beside the per-vendor prices.');
        $this->assertEqualsCanonicalizing(
            [250.00, 500.00],
            $rows->pluck('unit_price')->map(fn ($p) => (float) $p)->all()
        );
        $this->assertEquals(250.00, app(PriceResolver::class)->forSale($product)->unitPrice);
    }

    public function test_saving_the_same_average_twice_does_not_churn_the_history(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $payload = [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ];

        $this->put(route('product-pricing.sale.update', $product->id), $payload);
        $first = $this->standingRows($product, 'warehouse')->first();

        $this->put(route('product-pricing.sale.update', $product->id), $payload);
        $second = $this->standingRows($product, 'warehouse')->first();

        $this->assertSame($first->id, $second->id, 'Nothing moved, so nothing should have been superseded.');
    }

    public function test_the_editor_reopens_on_the_mode_the_prices_are_actually_in(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ]);

        $data = $this->pricing->editorData($product->refresh());

        $this->assertSame('average', $data['saleKinds']['warehouse']['mode']);
        $this->assertEquals(375.00, $data['saleKinds']['warehouse']['average']['unit_price']);
        $this->assertEquals(25.00, $data['saleKinds']['warehouse']['average']['markup_percent']);
    }

    public function test_a_price_typed_by_hand_with_no_vendor_basis_is_not_read_as_an_average(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        // The legacy shape: basis-less, but never derived from anything. It
        // must keep its own line rather than being mistaken for the average.
        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'charged_basis' => 'none',
                'lines' => ['none' => ['is_auto_derived' => '0', 'unit_price' => '540.00']],
            ]],
        ]);

        $data = $this->pricing->editorData($product->refresh());

        $this->assertSame('vendor', $data['saleKinds']['warehouse']['mode']);
        $this->assertContains(
            'No vendor basis',
            collect($data['saleKinds']['warehouse']['lines'])->pluck('vendor_name')->all()
        );
    }

    public function test_pricing_on_an_average_needs_a_cost_to_average(): void
    {
        $product = Product::factory()->create();

        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => ['warehouse' => [
                'enabled' => '1', 'mode' => 'average',
                'average' => ['markup_percent' => '25'],
            ]],
        ])->assertRedirect();

        $this->assertCount(
            0,
            $this->standingRows($product, 'warehouse'),
            'With nothing costed there is nothing to mark up, and a zero would be worse than no price.'
        );
    }

    /* ---------------------------------------------------------------------
     | The same price everywhere
     |---------------------------------------------------------------------*/

    public function test_one_block_can_price_every_fulfilment_kind(): void
    {
        [$product, $bases] = $this->productCarriedAt([400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'mirror' => '1',
            // Only the warehouse block is rendered while mirroring.
            'sale' => ['warehouse' => [
                'enabled' => '1',
                'charged_basis' => (string) $bases[0],
                'lines' => [$bases[0] => ['markup_percent' => '25', 'is_auto_derived' => '1']],
            ]],
        ])->assertRedirect();

        $resolver = app(PriceResolver::class);

        $this->assertEquals(500.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Warehouse::factory()->create(),
        ))->unitPrice);

        $this->assertEquals(500.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Retailer::factory()->create(),
        ))->unitPrice, 'The retailer block was never posted, so it was filled in from the warehouse one.');
    }

    public function test_the_same_price_everywhere_works_on_an_average_too(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $this->put(route('product-pricing.sale.update', $product->id), [
            'mirror' => '1',
            'sale' => ['warehouse' => [
                'enabled' => '1', 'mode' => 'average',
                'average' => ['markup_percent' => '10'],
            ]],
        ])->assertRedirect();

        foreach (['warehouse', 'retailer'] as $kind) {
            $rows = $this->standingRows($product, $kind);
            $this->assertCount(1, $rows, "One averaged price for {$kind}.");
            $this->assertEquals(330.00, $rows->first()->unit_price);
        }
    }

    public function test_prices_stay_stored_per_kind_so_they_can_be_split_again(): void
    {
        [$product, $bases] = $this->productCarriedAt([400.00]);

        $shared = fn (string $markup) => [
            'enabled' => '1',
            'charged_basis' => (string) $bases[0],
            'lines' => [$bases[0] => ['markup_percent' => $markup, 'is_auto_derived' => '1']],
        ];

        $this->put(route('product-pricing.sale.update', $product->id), [
            'mirror' => '1',
            'sale' => ['warehouse' => $shared('25')],
        ]);

        $this->assertTrue($this->pricing->editorData($product->refresh())['mirrored']);

        // Turning it off and pricing them apart needs nothing unpicked.
        $this->put(route('product-pricing.sale.update', $product->id), [
            'mirror' => '0',
            'sale' => [
                'warehouse' => $shared('25'),
                'retailer' => $shared('40'),
            ],
        ])->assertRedirect();

        $resolver = app(PriceResolver::class);

        $this->assertEquals(500.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Warehouse::factory()->create(),
        ))->unitPrice);
        $this->assertEquals(560.00, $resolver->forSale($product, new PriceContext(
            fulfilmentLocation: Retailer::factory()->create(),
        ))->unitPrice);

        $this->assertFalse($this->pricing->editorData($product->refresh())['mirrored']);
    }

    public function test_the_switch_reflects_whether_the_kinds_are_actually_priced_alike(): void
    {
        [$product, $bases] = $this->productCarriedAt([400.00]);

        // Priced apart by hand, without ever touching the switch.
        $this->put(route('product-pricing.sale.update', $product->id), [
            'sale' => [
                'warehouse' => [
                    'enabled' => '1', 'charged_basis' => (string) $bases[0],
                    'lines' => [$bases[0] => ['markup_percent' => '25', 'is_auto_derived' => '1']],
                ],
                'retailer' => [
                    'enabled' => '1', 'charged_basis' => (string) $bases[0],
                    'lines' => [$bases[0] => ['markup_percent' => '40', 'is_auto_derived' => '1']],
                ],
            ],
        ]);

        $this->assertFalse(
            $this->pricing->editorData($product->refresh())['mirrored'],
            'The switch describes the prices, rather than the prices following the switch.'
        );
    }

    public function test_a_kind_with_no_price_at_all_is_not_reported_as_matching(): void
    {
        [$product] = $this->productCarriedAt([400.00]);

        $this->assertFalse(
            $this->pricing->editorData($product)['mirrored'],
            'Nothing priced anywhere is not the same as one price everywhere.'
        );
    }

    public function test_the_editor_offers_both_shortcuts(): void
    {
        [$product] = $this->productCarriedAt([200.00, 400.00]);

        $this->get(route('product-pricing.edit', $product->id))
            ->assertOk()
            ->assertSee('Charge the same price at every fulfilment location')
            ->assertSee('One price from the average')
            ->assertSee('Average purchase price')
            // The mean of 200 and 400, shown as the figure the markup applies to.
            ->assertSee('300.00');
    }
}
