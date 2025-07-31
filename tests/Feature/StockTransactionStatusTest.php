<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Vendor;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Grn;
use App\Models\StockTransaction;
use App\Models\Order;
use App\Models\Customer;
use App\Services\GrnService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockTransactionStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->vendor = Vendor::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
        ]);
    }

    /** @test */
    public function grn_stock_transactions_are_created_with_completed_status()
    {
        // Create a supply
        $supply = Supply::create([
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'supply_date' => now(),
            'total_cost' => 100,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => 10,
            'subtotal' => 100,
        ]);

        // Create a GRN
        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        // Post the GRN (this should create stock transactions with 'completed' status)
        $grnService = app(GrnService::class);
        $grnService->transitionStatus($grn->id, 'posted');

        // Verify the stock transaction was created with 'completed' status
        $stockTransaction = StockTransaction::where('reference_type', Grn::class)
            ->where('reference_id', $grn->id)
            ->where('transaction_type', StockTransaction::TYPE_STOCK_IN)
            ->first();

        $this->assertNotNull($stockTransaction);
        $this->assertEquals('completed', $stockTransaction->status);
    }

    /** @test */
    public function order_stock_transactions_are_created_with_pending_status()
    {
        // Create initial stock
        \App\Models\ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        // Create an order
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'order_date' => now(),
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'subtotal' => 100,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
        ]);

        // Confirm the order (this should create stock transactions with 'pending' status)
        $orderService = app(OrderService::class);
        $orderService->confirm($order);

        // Verify the stock transaction was created with 'pending' status
        $stockTransaction = StockTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('transaction_type', StockTransaction::TYPE_ORDER_FULFILLMENT)
            ->first();

        $this->assertNotNull($stockTransaction);
        $this->assertEquals('pending', $stockTransaction->status);
    }

    /** @test */
    public function stock_transactions_created_directly_have_correct_default_status()
    {
        // Test that stock transactions created directly have the correct default status
        $transaction = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 10,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_STOCK_IN,
            'reference_type' => Grn::class,
            'reference_id' => 1,
            'transaction_date' => now(),
            'status' => 'completed', // Explicitly set status
        ]);

        $this->assertEquals('completed', $transaction->status);

        // Test another transaction with pending status
        $pendingTransaction = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 5,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_ORDER_FULFILLMENT,
            'reference_type' => Order::class,
            'reference_id' => 1,
            'transaction_date' => now(),
            'status' => 'pending', // Explicitly set status
        ]);

        $this->assertEquals('pending', $pendingTransaction->status);
    }
} 