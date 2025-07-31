<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrossProfitCalculationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function gross_profit_is_calculated_when_order_is_confirmed(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $retailer = Retailer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 50.00,
            'selling_price' => 75.00,
            'gross_profit' => null, // Start with null
        ]);

        // Create an order with one item
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'order_date' => now(),
            'total_amount' => 75.00,
            'fulfillment_location_id' => $retailer->id,
            'fulfillment_location_type' => Retailer::class,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'location_id' => $retailer->id,
            'location_type' => Retailer::class,
            'quantity' => 1,
            'unit_price' => 75.00,
            'subtotal' => 75.00,
        ]);

        // Action: Confirm the order
        $order->update(['status' => 'confirmed']);

        // Refresh the product to get updated data
        $product->refresh();

        // Assert that gross profit was calculated correctly
        // Expected: Revenue (75.00) - COGS (50.00) = 25.00
        $this->assertEquals(25.00, $product->gross_profit, 'Gross profit should be calculated as Revenue - COGS');
        $this->assertEquals(25.00, $product->gp, 'GP attribute should return the calculated gross profit');
    }

    /** @test */
    public function gross_profit_calculation_handles_multiple_quantities(): void
    {
        // Create test data
        $customer = Customer::factory()->create();
        $retailer = Retailer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 30.00,
            'selling_price' => 45.00,
            'gross_profit' => null,
        ]);

        // Create an order with multiple quantities
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'order_date' => now(),
            'total_amount' => 90.00, // 2 * 45.00
            'fulfillment_location_id' => $retailer->id,
            'fulfillment_location_type' => Retailer::class,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'location_id' => $retailer->id,
            'location_type' => Retailer::class,
            'quantity' => 2,
            'unit_price' => 45.00,
            'subtotal' => 90.00,
        ]);

        // Action: Confirm the order
        $order->update(['status' => 'confirmed']);

        // Refresh the product to get updated data
        $product->refresh();

        // Assert that gross profit per unit was calculated correctly
        // Total Revenue: 2 * 45.00 = 90.00
        // Total COGS: 2 * 30.00 = 60.00
        // Total Gross Profit: 90.00 - 60.00 = 30.00
        // Gross Profit per unit: 30.00 / 2 = 15.00
        $this->assertEquals(15.00, $product->gross_profit, 'Gross profit per unit should be calculated correctly');
        $this->assertEquals(15.00, $product->gp, 'GP attribute should return the calculated gross profit per unit');
    }

    /** @test */
    public function gp_attribute_returns_null_for_products_without_confirmed_orders(): void
    {
        $product = Product::factory()->create([
            'purchase_price' => 50.00,
            'selling_price' => 75.00,
            'gross_profit' => null,
        ]);

        // Product has no orders, so GP should be null
        $this->assertNull($product->gp, 'GP should be null for products without confirmed orders');
    }

    /** @test */
    public function gp_attribute_returns_stored_gross_profit_for_products_with_confirmed_orders(): void
    {
        $customer = Customer::factory()->create();
        $retailer = Retailer::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 50.00,
            'selling_price' => 75.00,
            'gross_profit' => 25.00, // Pre-stored gross profit
        ]);

        // Create a confirmed order for this product
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'order_date' => now(),
            'total_amount' => 75.00,
            'fulfillment_location_id' => $retailer->id,
            'fulfillment_location_type' => Retailer::class,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'location_id' => $retailer->id,
            'location_type' => Retailer::class,
            'quantity' => 1,
            'unit_price' => 75.00,
            'subtotal' => 75.00,
        ]);

        // GP should return the stored gross profit
        $this->assertEquals(25.00, $product->gp, 'GP should return the stored gross profit amount');
    }
} 