<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\StockTransaction;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnifiedReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected Customer $customer;
    protected Vendor $vendor;
    protected Retailer $retailer;
    protected Warehouse $warehouse;
    protected Invoice $invoice;
    protected SupplierBill $supplierBill;
    protected StockTransfer $stockTransfer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test data
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'purchase_price' => 50.00,
        ]);
        
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
        ]);
        
        $this->vendor = Vendor::factory()->create([
            'name' => 'Test Vendor',
        ]);
        
        $this->retailer = Retailer::factory()->create([
            'name' => 'Test Retailer',
        ]);
        
        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Test Warehouse',
        ]);
        
        // Create test invoice
        $this->invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'paid',
        ]);

        // Create test supplier bill
        $this->supplierBill = SupplierBill::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'posted',
        ]);
        
        // Create test stock transfer
        $this->stockTransfer = StockTransfer::factory()->create([
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => Retailer::class,
            'to_location_id' => $this->retailer->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_can_create_customer_return_using_stock_transaction()
    {
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $return = $returnService->createCustomerReturn($returnData);

        // Assert return was created as StockTransaction
        $this->assertInstanceOf(StockTransaction::class, $return);
        $this->assertEquals(StockTransaction::TYPE_CUSTOMER_RETURN, $return->transaction_type);
        $this->assertEquals(StockTransaction::STATUS_PENDING, $return->status);
        $this->assertEquals('inbound', $return->direction);
        $this->assertEquals(2, $return->quantity);
        $this->assertEquals('Defective product', $return->return_reason);
        $this->assertEquals($this->warehouse->id, $return->location_id);
        $this->assertEquals(Warehouse::class, $return->location_type);
        $this->assertEquals(Invoice::class, $return->reference_type);
        $this->assertEquals($this->invoice->id, $return->reference_id);
    }

    /** @test */
    public function it_can_create_vendor_return_using_stock_transaction()
    {
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'supplier_bill_id' => $this->supplierBill->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'return_reason' => 'Wrong specification',
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $return = $returnService->createVendorReturn($returnData);

        // Assert return was created as StockTransaction
        $this->assertInstanceOf(StockTransaction::class, $return);
        $this->assertEquals(StockTransaction::TYPE_VENDOR_RETURN, $return->transaction_type);
        $this->assertEquals(StockTransaction::STATUS_PENDING, $return->status);
        $this->assertEquals('outbound', $return->direction);
        $this->assertEquals(3, $return->quantity);
        $this->assertEquals('Wrong specification', $return->return_reason);
        $this->assertEquals($this->warehouse->id, $return->location_id);
        $this->assertEquals(Warehouse::class, $return->location_type);
        $this->assertEquals(SupplierBill::class, $return->reference_type);
        $this->assertEquals($this->supplierBill->id, $return->reference_id);
    }

    /** @test */
    public function it_can_create_retailer_return_using_stock_transaction()
    {
        $returnService = app(ReturnService::class);
        
        $returnData = [
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'return_reason' => 'Excess inventory',
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $return = $returnService->createRetailerReturn($returnData);

        // Assert return was created as StockTransaction
        $this->assertInstanceOf(StockTransaction::class, $return);
        $this->assertEquals(StockTransaction::TYPE_RETAILER_RETURN, $return->transaction_type);
        $this->assertEquals(StockTransaction::STATUS_PENDING, $return->status);
        $this->assertEquals('inbound', $return->direction);
        $this->assertEquals(1, $return->quantity);
        $this->assertEquals('Excess inventory', $return->return_reason);
        $this->assertEquals($this->warehouse->id, $return->location_id);
        $this->assertEquals(Warehouse::class, $return->location_type);
        $this->assertEquals(StockTransfer::class, $return->reference_type);
        $this->assertEquals($this->stockTransfer->id, $return->reference_id);
    }

    /** @test */
    public function it_can_approve_customer_return()
    {
        $returnService = app(ReturnService::class);
        
        // Create a customer return
        $returnData = [
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $return = $returnService->createCustomerReturn($returnData);

        // Approve the return
        $approvedReturn = $returnService->approveReturn($return);

        // Assert return was approved
        $this->assertEquals(StockTransaction::STATUS_APPROVED, $approvedReturn->status);
        $this->assertNotNull($approvedReturn->approved_by);
        $this->assertNotNull($approvedReturn->approved_at);
        $this->assertEquals($this->user->id, $approvedReturn->approved_by);
    }

    /** @test */
    public function it_can_complete_approved_return()
    {
        $returnService = app(ReturnService::class);
        
        // Create and approve a customer return
        $returnData = [
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $return = $returnService->createCustomerReturn($returnData);
        $approvedReturn = $returnService->approveReturn($return);

        // Complete the return
        $completedReturn = $returnService->completeReturn($approvedReturn);

        // Assert return was completed
        $this->assertEquals(StockTransaction::STATUS_COMPLETED, $completedReturn->status);
        $this->assertNotNull($completedReturn->completed_at);
    }

    /** @test */
    public function it_validates_return_quantities()
    {
        $returnService = app(ReturnService::class);
        
        // Test customer return validation
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            5 // Assuming this exceeds available quantity
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_gets_return_statistics()
    {
        $returnService = app(ReturnService::class);
        
        // Create some test returns
        $returnData = [
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ];

        $returnService->createCustomerReturn($returnData);

        $statistics = $returnService->getReturnStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_returns', $statistics);
        $this->assertArrayHasKey('customer_returns', $statistics);
        $this->assertArrayHasKey('vendor_returns', $statistics);
        $this->assertArrayHasKey('retailer_returns', $statistics);
        $this->assertArrayHasKey('pending_returns', $statistics);
        $this->assertArrayHasKey('approved_returns', $statistics);
        $this->assertArrayHasKey('completed_returns', $statistics);
        $this->assertArrayHasKey('total_return_amount', $statistics);
    }

    /** @test */
    public function it_gets_filter_options()
    {
        $returnService = app(ReturnService::class);
        
        $filterOptions = $returnService->getFilterOptions();

        $this->assertIsArray($filterOptions);
        $this->assertArrayHasKey('types', $filterOptions);
        $this->assertArrayHasKey('statuses', $filterOptions);
        $this->assertArrayHasKey('locations', $filterOptions);
    }

    /** @test */
    public function it_gets_sort_options()
    {
        $returnService = app(ReturnService::class);
        
        $sortOptions = $returnService->getSortOptions();

        $this->assertIsArray($sortOptions);
        $this->assertNotEmpty($sortOptions);
    }

    /** @test */
    public function it_gets_page_title()
    {
        $returnService = app(ReturnService::class);
        
        $title = $returnService->getPageTitle();
        $this->assertEquals('All Returns', $title);

        $customerTitle = $returnService->getPageTitle('customer_return');
        $this->assertEquals('Customer Returns', $customerTitle);

        $vendorTitle = $returnService->getPageTitle('vendor_return');
        $this->assertEquals('Vendor Returns', $vendorTitle);

        $retailerTitle = $returnService->getPageTitle('retailer_return');
        $this->assertEquals('Retailer Returns', $retailerTitle);
    }

    /** @test */
    public function stock_transaction_model_has_return_methods()
    {
        // Create a customer return
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 2,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_PENDING,
            'return_reason' => 'Test return',
        ]);

        // Test return type checks
        $this->assertTrue($return->isReturn());
        $this->assertTrue($return->isCustomerReturn());
        $this->assertFalse($return->isVendorReturn());
        $this->assertFalse($return->isRetailerReturn());

        // Test status checks
        $this->assertTrue($return->isPending());
        $this->assertFalse($return->isApproved());
        $this->assertFalse($return->isCompleted());

        // Test UI helper methods
        $this->assertEquals('warning', $return->getStatusBadgeClass());
        $this->assertEquals('Customer Return', $return->getReturnTypeLabel());
        $this->assertEquals('danger', $return->getReturnTypeBadgeClass());
    }

    /** @test */
    public function it_can_approve_return_using_model_method()
    {
        // Create a customer return
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 2,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_PENDING,
            'return_reason' => 'Test return',
        ]);

        // Approve using model method
        $return->approve($this->user->id, 'Approved for testing');

        // Assert approval
        $this->assertEquals(StockTransaction::STATUS_APPROVED, $return->status);
        $this->assertEquals($this->user->id, $return->approved_by);
        $this->assertNotNull($return->approved_at);
    }

    /** @test */
    public function it_can_reject_return_using_model_method()
    {
        // Create a customer return
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 2,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_PENDING,
            'return_reason' => 'Test return',
        ]);

        // Reject using model method
        $return->reject($this->user->id, 'Rejected for testing');

        // Assert rejection
        $this->assertEquals(StockTransaction::STATUS_REJECTED, $return->status);
        $this->assertEquals($this->user->id, $return->rejected_by);
        $this->assertNotNull($return->rejected_at);
        $this->assertEquals('Rejected for testing', $return->rejection_reason);
    }

    /** @test */
    public function it_can_complete_return_using_model_method()
    {
        // Create and approve a customer return
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 2,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_APPROVED,
            'return_reason' => 'Test return',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Complete using model method
        $return->complete($this->user->id, 'Completed for testing');

        // Assert completion
        $this->assertEquals(StockTransaction::STATUS_COMPLETED, $return->status);
        $this->assertNotNull($return->completed_at);
    }

    /** @test */
    public function it_can_cancel_return_using_model_method()
    {
        // Create a customer return
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 2,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_PENDING,
            'return_reason' => 'Test return',
        ]);

        // Cancel using model method
        $return->cancel($this->user->id, 'Cancelled for testing');

        // Assert cancellation
        $this->assertEquals(StockTransaction::STATUS_CANCELLED, $return->status);
    }
} 