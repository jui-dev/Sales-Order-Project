<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\Vendor;
use App\Services\Pricing\PriceContext;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\PriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Which price applies, to whom, and when.
 *
 * The rules pinned here are the ones the old single-column design could not
 * express at all: the same product priced differently per customer, per group,
 * per channel and per quantity, and a price change that leaves last month's
 * price still readable at last month's date.
 */
class PriceResolutionTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $lists;
    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = app(PriceListService::class);
        $this->resolver = app(PriceResolver::class);
    }

    /**
     * The pricing migration already ships a default "retail" list, so reuse it
     * where the code matches rather than colliding with it.
     */
    private function saleList(string $code, int $priority = 0, bool $default = false): PriceList
    {
        $existing = PriceList::where('code', $code)->first();

        if ($existing) {
            $existing->update(['priority' => $priority]);

            return $default ? $this->lists->makeDefault($existing) : $existing->refresh();
        }

        return $this->lists->create([
            'name' => ucfirst($code),
            'code' => $code,
            'type' => PriceList::TYPE_SALE,
            'priority' => $priority,
            'is_default' => $default,
        ]);
    }

    public function test_the_base_list_applies_when_nothing_more_specific_does(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);
        $this->lists->setPrice($retail, $product, 100.00);

        $price = $this->resolver->forSale($product, new PriceContext(
            customer: Customer::factory()->create(),
        ));

        $this->assertEquals(100.00, $price->unitPrice);
        $this->assertSame('price_list', $price->source);
    }

    public function test_a_customers_own_rate_beats_their_groups_which_beats_the_base_list(): void
    {
        $product = Product::factory()->create();
        $group = CustomerGroup::create(['name' => 'Wholesale', 'code' => 'wholesale']);
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $retail = $this->saleList('retail', 0, true);
        $this->lists->setPrice($retail, $product, 100.00);

        $wholesale = $this->saleList('wholesale', 50);
        $this->lists->assignTo($wholesale, $group);
        $this->lists->setPrice($wholesale, $product, 80.00);

        $context = new PriceContext(customer: $customer->fresh());
        $this->assertEquals(80.00, $this->resolver->forSale($product, $context)->unitPrice);

        // Now negotiate a rate with this one customer.
        $special = $this->saleList('special', 100);
        $this->lists->assignTo($special, $customer);
        $this->lists->setPrice($special, $product, 72.50);

        $this->assertEquals(72.50, $this->resolver->forSale($product, $context)->unitPrice);
    }

    public function test_a_quantity_break_applies_only_once_it_is_earned(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);

        $this->lists->setPrice($retail, $product, 100.00, 1);
        $this->lists->setPrice($retail, $product, 90.00, 10);

        $this->assertEquals(100.00, $this->resolver->forSale($product, new PriceContext(quantity: 9))->unitPrice);
        $this->assertEquals(90.00, $this->resolver->forSale($product, new PriceContext(quantity: 10))->unitPrice);
    }

    public function test_a_price_change_leaves_the_old_price_readable_at_its_own_date(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);

        $this->lists->setPrice($retail, $product, 100.00, 1, Carbon::parse('2026-01-01'));
        $this->lists->setPrice($retail, $product, 130.00, 1, Carbon::parse('2026-06-01'));

        $this->assertEquals(
            100.00,
            $this->resolver->forSale($product, new PriceContext(at: Carbon::parse('2026-03-01')))->unitPrice,
            'Raising the price in June must not restate what March cost.'
        );

        $this->assertEquals(
            130.00,
            $this->resolver->forSale($product, new PriceContext(at: Carbon::parse('2026-07-01')))->unitPrice
        );
    }

    public function test_a_promotion_stops_applying_once_it_has_run_out(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);
        $this->lists->setPrice($retail, $product, 100.00, 1, Carbon::parse('2026-01-01'));

        $promo = $this->lists->create([
            'name' => 'Summer Sale',
            'code' => 'summer',
            'type' => PriceList::TYPE_SALE,
            'priority' => 75,
            'starts_at' => Carbon::parse('2026-06-01'),
            'ends_at' => Carbon::parse('2026-06-30'),
        ]);
        $this->lists->setPrice($promo, $product, 60.00, 1, Carbon::parse('2026-06-01'));

        $during = new PriceContext(at: Carbon::parse('2026-06-15'));
        $after = new PriceContext(at: Carbon::parse('2026-07-15'));

        $this->assertEquals(60.00, $this->resolver->forSale($product, $during)->unitPrice);
        $this->assertEquals(100.00, $this->resolver->forSale($product, $after)->unitPrice);
    }

    public function test_a_channel_can_carry_its_own_price(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);
        $this->lists->setPrice($retail, $product, 100.00);

        $channel = SalesChannel::create(['name' => 'Marketplace', 'code' => 'marketplace']);
        $marketplace = $this->saleList('marketplace', 40);
        $this->lists->assignTo($marketplace, $channel);
        $this->lists->setPrice($marketplace, $product, 115.00);

        $this->assertEquals(
            115.00,
            $this->resolver->forSale($product, new PriceContext(salesChannel: $channel))->unitPrice
        );
        $this->assertEquals(100.00, $this->resolver->forSale($product)->unitPrice);
    }

    public function test_different_vendors_can_charge_different_amounts_for_one_product(): void
    {
        $product = Product::factory()->create();
        $acme = Vendor::factory()->create(['name' => 'Acme']);
        $globex = Vendor::factory()->create(['name' => 'Globex']);

        foreach ([[$acme, 50.00, 'acme'], [$globex, 47.50, 'globex']] as [$vendor, $cost, $code]) {
            $list = $this->lists->create([
                'name' => 'Vendor: '.$vendor->name,
                'code' => $code,
                'type' => PriceList::TYPE_PURCHASE,
            ]);
            $this->lists->assignTo($list, $vendor);
            $this->lists->setPrice($list, $product, $cost);
        }

        $this->assertEquals(50.00, $this->resolver->forPurchase($product, $acme)->unitPrice);
        $this->assertEquals(47.50, $this->resolver->forPurchase($product, $globex)->unitPrice);
    }

    public function test_a_vendors_price_rise_does_not_restate_an_earlier_order(): void
    {
        $product = Product::factory()->create();
        $vendor = Vendor::factory()->create();
        $list = $this->lists->create([
            'name' => 'Vendor', 'code' => 'v1', 'type' => PriceList::TYPE_PURCHASE,
        ]);
        $this->lists->assignTo($list, $vendor);

        $this->lists->setPrice($list, $product, 400.00, 1, Carbon::parse('2026-01-01'));
        $this->lists->setPrice($list, $product, 200.00, 1, Carbon::parse('2026-06-01'));

        $this->assertEquals(
            400.00,
            $this->resolver->forPurchase($product, $vendor, Carbon::parse('2026-02-01'))->unitPrice
        );
        $this->assertEquals(
            200.00,
            $this->resolver->forPurchase($product, $vendor, Carbon::parse('2026-08-01'))->unitPrice
        );
    }

    public function test_setting_the_same_price_again_does_not_churn_the_history(): void
    {
        $product = Product::factory()->create();
        $retail = $this->saleList('retail', 0, true);

        $first = $this->lists->setPrice($retail, $product, 100.00);
        $second = $this->lists->setPrice($retail, $product, 100.00);

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $this->lists->historyFor($product));
    }

    public function test_only_one_list_per_type_can_be_the_default(): void
    {
        $first = $this->saleList('retail', 0, true);
        $second = $this->saleList('other', 0, true);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
        $this->assertSame(1, PriceList::ofType(PriceList::TYPE_SALE)->where('is_default', true)->count());
    }

    public function test_an_unpriced_product_falls_back_to_cost_plus_markup(): void
    {
        $product = Product::factory()->create(['purchase_price' => 100.00, 'markup' => 25]);

        $price = $this->resolver->forSale($product);

        $this->assertEquals(125.00, $price->unitPrice);
        $this->assertTrue($price->isDerived(), 'A derived price must be distinguishable from an agreed one.');
    }

    public function test_a_product_with_neither_a_price_nor_a_cost_has_no_price(): void
    {
        $product = Product::factory()->create(['purchase_price' => 0, 'selling_price' => 0]);

        $this->assertNull(
            $this->resolver->forSale($product),
            'Inventing a zero would let something be sold for nothing.'
        );
    }
}
