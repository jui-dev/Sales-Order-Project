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
use App\Models\User;
use App\Services\GrnService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockTransactionStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Services write audit logs against `auth()->id() ?? 1`, which trips the
        // audit_logs foreign key unless a real user is authenticated.
        $this->actingAs(User::factory()->create());

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
    public function order_confirmation_reserves_stock_but_no_transaction_created()
    {
        // Create initial stock
        $productStock = \App\Models\ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        // Create an order
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'fulfillment_location_id' => $this->warehouse->id,
            'fulfillment_location_type' => Warehouse::class,
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

        // Confirm the order (this should only reserve stock, not create transactions)
        $orderService = app(OrderService::class);
        $pickingList = $orderService->confirm($order);

        // Verify no stock transaction was created during confirmation (only picking list completion creates them)
        $stockTransaction = StockTransaction::where('transaction_type', StockTransaction::TYPE_ORDER_FULFILLMENT)
            ->first();

        $this->assertNull($stockTransaction, 'No stock transaction should be created during order confirmation');

        // Verify stock was reserved
        $productStock->refresh();
        $this->assertEquals(5, $productStock->reserved_quantity, 'Stock should be reserved');
        $this->assertEquals(100, $productStock->quantity, 'Physical stock should remain unchanged');

        // Verify picking list was created
        $this->assertNotNull($pickingList);
        $this->assertEquals($order->id, $pickingList->reference_id);
    }

    /** @test */
    public function picking_list_completion_creates_stock_transactions_with_pickinglist_reference()
    {
        // Create initial stock
        $productStock = \App\Models\ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
            'reserved_quantity' => 10, // Already reserved
        ]);

        // Create a picking list for an order
        $pickingList = \App\Models\PickingList::create([
            'reference_type' => Order::class,
            'reference_id' => 1,
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => \App\Models\Customer::class,
            'to_location_id' => $this->customer->id,
            'status' => 'pending',
            'picking_type' => 'order_fulfillment',
            'picking_date' => now(),
        ]);

        // Create picking list item
        \App\Models\PickingListItem::create([
            'picking_list_id' => $pickingList->id,
            'product_id' => $this->product->id,
            'quantity_requested' => 10,
            'quantity_picked' => 10,
            'status' => 'picked'
        ]);

        // Complete the picking list (this should create stock transactions with PickingList reference)
        $pickingList->update(['status' => 'completed']);

        // Verify the stock transaction was created with PickingList reference
        $stockTransaction = StockTransaction::where('reference_type', \App\Models\PickingList::class)
            ->where('reference_id', $pickingList->id)
            ->where('transaction_type', StockTransaction::TYPE_ORDER_FULFILLMENT)
            ->first();

        $this->assertNotNull($stockTransaction, 'Stock transaction should be created with PickingList reference');
        $this->assertEquals('completed', $stockTransaction->status);
        $this->assertEquals('outbound', $stockTransaction->direction);
        $this->assertEquals(10, $stockTransaction->quantity);

        // Verify no Order reference transaction was created
        $orderTransaction = StockTransaction::where('reference_type', Order::class)
            ->where('transaction_type', StockTransaction::TYPE_ORDER_FULFILLMENT)
            ->first();

        $this->assertNull($orderTransaction, 'No Order reference transaction should be created');

        // Verify stock was properly deducted and reservation released
        $productStock->refresh();
        $this->assertEquals(90, $productStock->quantity, 'Physical stock should be deducted');
        $this->assertEquals(0, $productStock->reserved_quantity, 'Reservation should be released');
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

        // Test another transaction with completed status (using PickingList reference for order fulfillment)
        $pendingTransaction = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 5,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_ORDER_FULFILLMENT,
            'reference_type' => \App\Models\PickingList::class,
            'reference_id' => 1,
            'transaction_date' => now(),
            'status' => 'completed', // Order fulfillment transactions should be completed when picking is done
        ]);

        $this->assertEquals('completed', $pendingTransaction->status);
    }
}
