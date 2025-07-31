<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Models\Vendor;
use App\Models\SupplierBill;
use App\Models\Grn;
use App\Models\Supply;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VendorReturnStockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $warehouse;
    protected $vendor;
    protected $product;
    protected $supply;
    protected $grn;
    protected $supplierBill;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->warehouse = Warehouse::factory()->create();
        $this->vendor = Vendor::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
        ]);

        // Create supply
        $this->supply = Supply::factory()->create([
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        // Create GRN
        $this->grn = Grn::factory()->create([
            'supply_id' => $this->supply->id,
            'status' => 'completed',
        ]);

        // Create supplier bill
        $this->supplierBill = SupplierBill::factory()->create([
            'vendor_id' => $this->vendor->id,
            'grn_id' => $this->grn->id,
            'status' => 'posted',
        ]);

        // Add initial stock to warehouse
        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function vendor_return_should_not_create_vendor_location_in_product_stocks()
    {
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'supplier_bill_id' => $this->supplierBill->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'return_reason' => 'Defective product',
            'return_date' => now(),
        ];

        $return = $returnService->createVendorReturn($returnData);

        // Assert return was created correctly
        $this->assertInstanceOf(StockTransaction::class, $return);
        $this->assertEquals(StockTransaction::TYPE_VENDOR_RETURN, $return->transaction_type);
        $this->assertEquals('outbound', $return->direction);
        $this->assertEquals($this->warehouse->id, $return->location_id);
        $this->assertEquals(Warehouse::class, $return->location_type);

        // Verify no vendor location record was created in product_stocks
        $vendorStockRecord = ProductStock::where('location_type', Vendor::class)
            ->where('product_id', $this->product->id)
            ->first();
        
        $this->assertNull($vendorStockRecord, 'Vendor location record should not exist in product_stocks table');

        // Verify warehouse stock was decreased
        $warehouseStock = ProductStock::where('location_type', Warehouse::class)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->warehouse->id)
            ->first();
        
        $this->assertNotNull($warehouseStock);
        $this->assertEquals(90, $warehouseStock->quantity); // 100 - 10 = 90
    }

    /** @test */
    public function available_stocks_calculation_should_exclude_vendor_locations()
    {
        // Create a vendor return
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'supplier_bill_id' => $this->supplierBill->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'return_reason' => 'Defective product',
            'return_date' => now(),
        ];

        $return = $returnService->createVendorReturn($returnData);

        // Refresh the product to get updated available_stocks
        $this->product->refresh();

        // Available stocks should only consider internal locations (warehouses and retailers)
        // It should not include vendor locations
        $this->assertEquals(90, $this->product->available_stocks);

        // Verify that vendor locations are not counted in stockBalances
        $stockBalances = $this->product->stockBalances;
        $vendorBalances = $stockBalances->where('location_type', Vendor::class);
        
        $this->assertEquals(0, $vendorBalances->count(), 'No vendor balances should exist');
    }

    /** @test */
    public function vendor_return_should_only_affect_warehouse_stock()
    {
        // Create initial stock in multiple locations
        $retailer = \App\Models\Retailer::factory()->create();
        
        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $retailer->id,
            'location_type' => \App\Models\Retailer::class,
            'quantity' => 50,
        ]);

        // Create vendor return
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'supplier_bill_id' => $this->supplierBill->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'return_reason' => 'Defective product',
            'return_date' => now(),
        ];

        $return = $returnService->createVendorReturn($returnData);

        // Verify warehouse stock was decreased
        $warehouseStock = ProductStock::where('location_type', Warehouse::class)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->warehouse->id)
            ->first();
        
        $this->assertEquals(90, $warehouseStock->quantity); // 100 - 10 = 90

        // Verify retailer stock was not affected
        $retailerStock = ProductStock::where('location_type', \App\Models\Retailer::class)
            ->where('product_id', $this->product->id)
            ->where('location_id', $retailer->id)
            ->first();
        
        $this->assertEquals(50, $retailerStock->quantity); // Should remain unchanged

        // Verify total available stock calculation
        $this->product->refresh();
        $this->assertEquals(140, $this->product->available_stocks); // 90 + 50 = 140
    }
}