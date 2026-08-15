<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class StockCalculationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calculates_available_stock_correctly_from_product_stocks_only()
    {
        // Create test data
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
        ]);

        // Create initial stock in product_stocks table
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        // Create a customer return transaction (should not affect available_stocks)
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
        ]);

        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 10,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        // Refresh the product to get updated available_stocks
        $product->refresh();

        // Available stock should be: 100 (from product_stocks only)
        // Stock transactions are not considered in the calculation
        $this->assertEquals(100, $product->available_stocks);
    }

    /** @test */
    public function it_handles_multiple_product_stock_entries_correctly()
    {
        // Create test data
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
        ]);

        // Create stock in multiple warehouses
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse1->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse2->id,
            'location_type' => Warehouse::class,
            'quantity' => 50,
        ]);

        // Movements do reach the balances, but not all of them and not at the
        // same moment - see the assertion at the end of this test.
        $invoice = Invoice::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);

        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse1->id,
            'location_type' => Warehouse::class,
            'quantity' => 10,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse2->id,
            'location_type' => Warehouse::class,
            'quantity' => 5,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_ORDER_FULFILLMENT,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        // Refresh the product to get updated available_stocks
        $product->refresh();

        // 100 + 50 across the two warehouses, less the 5 that went out on the
        // order. The 10-unit customer return does not land here: StockTransactionObserver
        // skips returns on create, so recording one leaves inventory alone
        // until somebody approves it.
        $this->assertEquals(145, $product->available_stocks);
    }

    /** @test */
    public function it_updates_product_stock_when_product_stock_is_created()
    {
        // Create test data
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'available_stocks' => 0, // Set initial stock
        ]);

        // Create initial stock in product_stocks table
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        // Refresh the product to get updated available_stocks
        $product->refresh();

        // Available stock should be: 100 (from product_stocks only)
        // Stock transactions are not considered in the calculation
        $this->assertEquals(100, $product->available_stocks);
    }

    /** @test */
    public function it_updates_product_stock_when_product_stock_quantity_changes()
    {
        // Create test data
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'available_stocks' => 100,
        ]);

        // Create initial stock
        $productStock = ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        // Refresh the product
        $product->refresh();

        // Available stock should be: 100 (from product_stocks only)
        $this->assertEquals(100, $product->available_stocks);

        // Update product stock quantity
        $productStock->update(['quantity' => 150]);

        // Refresh the product
        $product->refresh();

        // Available stock should be: 150 (from updated product_stocks only)
        $this->assertEquals(150, $product->available_stocks);
    }



    /** @test */
    public function it_recalculates_product_stock_correctly()
    {
        // recalculateProductStock treats stock_transactions as the record of
        // truth: it zeroes the balances and replays every completed movement.
        // So this test corrupts the balances and checks the ledger restores them.
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 0,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);

        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_ADJUSTMENT,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 30,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_ORDER_FULFILLMENT,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        // Knock both the balance and the cached figure out of line with the
        // ledger, the drift the recalculation exists to repair.
        DB::table('product_stocks')
            ->where('product_id', $product->id)
            ->update(['quantity' => 999]);
        DB::table('products')
            ->where('id', $product->id)
            ->update(['available_stocks' => 999]);

        app(ProductService::class)->recalculateProductStock($product->fresh());

        // 100 in, 30 out - rebuilt from the movements, not from what the
        // balance happened to say.
        $this->assertEquals(70, $product->fresh()->available_stocks);
        $this->assertEquals(70, DB::table('product_stocks')
            ->where('product_id', $product->id)
            ->value('quantity'));
    }

    /** @test */
    public function it_handles_vendor_returns_correctly()
    {
        // Create test data
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'available_stocks' => 100,
        ]);

        // Create initial stock
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);

        // Create a vendor return transaction (should not affect available_stocks)
        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 10,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_VENDOR_RETURN,
            'reference_type' => \App\Models\SupplierBill::class,
            'reference_id' => 1,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        // Refresh the product
        $product->refresh();

        // Available stock should be: 100 (from product_stocks only)
        // Stock transactions are not considered in the calculation
        $this->assertEquals(100, $product->available_stocks);
    }

    /** @test */
    public function it_calculates_supply_stock_correctly_without_double_counting()
    {
        // Create test data
        $warehouse = Warehouse::factory()->create();
        $vendor = \App\Models\Vendor::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'available_stocks' => 0,
        ]);

        // Simulate a supply of 300 units (like the user's scenario)
        // This would typically happen through the GRN posting process
        ProductStock::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 300,
        ]);

        // Create a stock transaction for the supply (should not affect available_stocks)
        StockTransaction::create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 300,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_STOCK_IN,
            'reference_type' => \App\Models\Grn::class,
            'reference_id' => 1,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        // Refresh the product
        $product->refresh();

        // Available stock should be: 300 (from product_stocks only)
        // NOT 600 (300 from product_stocks + 300 from stock_transactions)
        $this->assertEquals(300, $product->available_stocks);
    }
} 