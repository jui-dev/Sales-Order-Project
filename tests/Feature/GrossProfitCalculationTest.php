<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * products.gross_profit is the catalogue margin and nothing else.
 *
 * This file used to assert that confirming an order wrote the column from the
 * order line's margin. That writer was removed from OrderObserver - it made two
 * owners of one column, so the figure on the products page contradicted the two
 * prices printed beside it - but the assertions kept passing, because
 * ProductObserver infers the same markup back out of the fixture's prices. The
 * tests were green and proved nothing.
 *
 * What the column actually is: selling_price - purchase_price, owned by
 * ProductObserver, a property of the price list. Profit actually earned is a
 * different question with a different answer, and it is read off the ledger -
 * see Tests\Feature\Accounting\ProfitReportingTest.
 */
class GrossProfitCalculationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function gross_profit_is_the_margin_between_the_two_prices(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 50.00,
            'selling_price'  => 75.00,
        ]);

        $this->assertEquals(25.00, $product->fresh()->gross_profit);
    }

    /** @test */
    public function it_is_derived_from_the_markup_when_only_a_cost_is_given(): void
    {
        config(['pricing.default_markup' => 25]);

        $product = Product::factory()->create([
            'purchase_price' => 80.00,
            'selling_price'  => 0,
            'markup'         => null,
        ]);

        // 80 marked up 25% is 100, so the margin is 20.
        $this->assertEquals(100.00, $product->fresh()->selling_price);
        $this->assertEquals(20.00, $product->fresh()->gross_profit);
    }

    /**
     * The behaviour this file used to assert the opposite of.
     *
     * @test
     */
    public function confirming_an_order_does_not_rewrite_it(): void
    {
        $customer = Customer::factory()->create();
        $retailer = Retailer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 50.00,
            'selling_price'  => 75.00,
        ]);

        $this->assertEquals(25.00, $product->fresh()->gross_profit);

        $order = Order::create([
            'customer_id'               => $customer->id,
            'status'                    => 'pending',
            'order_date'                => now(),
            'total_amount'              => 200.00,
            'fulfillment_location_id'   => $retailer->id,
            'fulfillment_location_type' => Retailer::class,
        ]);

        // Sold well above list. If confirming still wrote this column, the
        // catalogue margin would jump to 150 and disagree with the two prices
        // shown beside it on the products page.
        OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'location_id'   => $retailer->id,
            'location_type' => Retailer::class,
            'quantity'      => 1,
            'unit_price'    => 200.00,
            'subtotal'      => 200.00,
        ]);

        $order->update(['status' => 'confirmed']);

        $this->assertEquals(25.00, $product->fresh()->gross_profit);
    }

    /** @test */
    public function a_product_with_no_cost_yet_is_left_alone(): void
    {
        // purchase_price is NOT NULL DEFAULT 0.00, so a product that has never
        // been received reads as zero. Deriving from that would zero the
        // selling price on every save, so zero means "not priced yet".
        $product = Product::factory()->create([
            'purchase_price' => 0,
            'selling_price'  => 90.00,
        ]);

        $this->assertEquals(90.00, $product->fresh()->selling_price);
    }
}
