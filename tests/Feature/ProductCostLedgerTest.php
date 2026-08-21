<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Pricing\ProductCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Inventory is valued at what it actually cost, and stays valued that way.
 *
 * The old code took the newest delivery's unit cost and wrote it over
 * products.purchase_price, so a small cheap delivery restated stock that had
 * cost far more - and, because every reader joined back to that column live,
 * re-valued sales and returns that had already happened.
 */
class ProductCostLedgerTest extends TestCase
{
    use RefreshDatabase;

    private ProductCostService $costs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costs = app(ProductCostService::class);
    }

    public function test_a_small_cheap_delivery_does_not_restate_the_stock_already_held(): void
    {
        $product = Product::factory()->create();

        // The live case that motivated this: 50 @ 400, then 5 @ 200.
        $this->costs->recordReceipt($product, 50, 400.00);
        $this->costs->recordReceipt($product, 5, 200.00);

        // (50*400 + 5*200) / 55 = 381.8182 - not 200.
        $this->assertEqualsWithDelta(381.8182, $this->costs->costAt($product), 0.0001);
    }

    public function test_the_first_receipt_sets_the_cost_outright(): void
    {
        $product = Product::factory()->create();

        $this->costs->recordReceipt($product, 10, 42.50);

        $this->assertEquals(42.50, $this->costs->costAt($product));
    }

    public function test_cost_can_be_read_as_it_stood_on_a_past_date(): void
    {
        $product = Product::factory()->create();

        $this->costs->recordReceipt($product, 10, 100.00, Carbon::parse('2026-01-10'));
        $this->costs->recordReceipt($product, 10, 200.00, Carbon::parse('2026-06-01'));

        $this->assertEquals(
            100.00,
            $this->costs->costAt($product, Carbon::parse('2026-03-01')),
            'A later receipt must not change what the goods cost in March.'
        );

        // (10*100 + 10*200) / 20 = 150
        $this->assertEquals(150.00, $this->costs->costAt($product, Carbon::parse('2026-07-01')));
    }

    public function test_an_unknown_cost_is_null_rather_than_zero(): void
    {
        $product = Product::factory()->create();

        $this->assertNull(
            $this->costs->costAt($product),
            'Never having recorded a cost is not the same as the goods being free.'
        );
    }

    public function test_the_ledger_is_append_only(): void
    {
        $product = Product::factory()->create();

        $this->costs->recordReceipt($product, 10, 100.00);
        $this->costs->recordReceipt($product, 10, 200.00);

        $this->assertCount(2, $this->costs->historyFor($product));
    }
}
