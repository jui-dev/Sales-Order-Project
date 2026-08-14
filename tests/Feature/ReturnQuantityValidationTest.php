<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockTransaction;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ReturnQuantityValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'selling_price' => 100.00,
            'purchase_price' => 80.00,
        ]);
        
        $this->invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-001',
            'total' => 200.00,
        ]);
        
        $this->invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total' => 200.00,
        ]);
    }

    /** @test */
    public function it_validates_quantity_less_than_zero()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            -1
        );
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Return quantity must be greater than 0', $result['errors']);
    }

    /** @test */
    public function it_validates_quantity_equal_to_zero()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            0
        );
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Return quantity must be greater than 0', $result['errors']);
    }

    /** @test */
    public function it_validates_quantity_within_available_range()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            1
        );
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_validates_quantity_exceeding_available()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            3
        );
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds available quantity', $result['errors'][0]);
    }

    /** @test */
    public function it_validates_quantity_considering_already_returned()
    {
        // Create a previous return
        StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => 1,
            'location_type' => 'App\Models\Warehouse',
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);
        
        $returnService = app(ReturnService::class);
        
        // Try to return 2 more (total would be 3, but only 2 were originally sold)
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            2
        );
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds available quantity', $result['errors'][0]);
    }

    /** @test */
    public function it_validates_quantity_for_partial_return_after_previous_return()
    {
        // Create a previous return of 1 item
        StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => 1,
            'location_type' => 'App\Models\Warehouse',
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);
        
        $returnService = app(ReturnService::class);
        
        // Try to return the remaining 1 item
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            1
        );
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_handles_invalid_invoice_id()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            99999, // Non-existent invoice
            $this->product->id,
            1
        );
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Invoice not found', $result['errors']);
    }

    /** @test */
    public function it_handles_invalid_product_id()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            99999, // Non-existent product
            1
        );
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Product not found in invoice', $result['errors']);
    }

    /** @test */
    public function it_provides_detailed_error_messages()
    {
        $returnService = app(ReturnService::class);
        
        $result = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $this->invoice->id,
            $this->product->id,
            5 // Exceeds available quantity
        );
        
        $this->assertFalse($result['valid']);
        $errorMessage = $result['errors'][0];
        
        // Check that the error message contains detailed information
        $this->assertStringContainsString('Return quantity (5)', $errorMessage);
        $this->assertStringContainsString('exceeds available quantity', $errorMessage);
        $this->assertStringContainsString('Original: 2', $errorMessage);
        $this->assertStringContainsString('Already returned: 0', $errorMessage);
    }
} 