<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Warehouse;
use App\Models\StockTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ReturnPaginationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->customer = Customer::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
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
    public function it_returns_paginated_results()
    {
        // Create multiple return transactions
        for ($i = 1; $i <= 25; $i++) {
            StockTransaction::create([
                'product_id' => $this->product->id,
                'location_id' => $this->warehouse->id,
                'location_type' => Warehouse::class,
                'quantity' => 1,
                'direction' => 'inbound',
                'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
                'reference_type' => Invoice::class,
                'reference_id' => $this->invoice->id,
                'transaction_date' => now(),
                'status' => 'pending',
            ]);
        }

        // Test the index page returns paginated results
        $response = $this->get(route('returns.index'));
        
        $response->assertStatus(200);
        $response->assertViewHas('returns');
        
        // Check that returns is a paginated result
        $returns = $response->viewData('returns');
        $this->assertTrue(method_exists($returns, 'links'));
        $this->assertEquals(20, $returns->count()); // Default per page is 20
        $this->assertEquals(25, $returns->total()); // Total records
    }

    /** @test */
    public function it_respects_per_page_parameter()
    {
        // Create multiple return transactions
        for ($i = 1; $i <= 25; $i++) {
            StockTransaction::create([
                'product_id' => $this->product->id,
                'location_id' => $this->warehouse->id,
                'location_type' => Warehouse::class,
                'quantity' => 1,
                'direction' => 'inbound',
                'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
                'reference_type' => Invoice::class,
                'reference_id' => $this->invoice->id,
                'transaction_date' => now(),
                'status' => 'pending',
            ]);
        }

        // Test with custom per_page parameter
        $response = $this->get(route('returns.index', ['per_page' => 10]));
        
        $response->assertStatus(200);
        $response->assertViewHas('returns');
        
        $returns = $response->viewData('returns');
        $this->assertEquals(10, $returns->count()); // Custom per page
        $this->assertEquals(25, $returns->total()); // Total records
    }

    /** @test */
    public function it_handles_filters_with_pagination()
    {
        // Create return transactions with different types
        for ($i = 1; $i <= 15; $i++) {
            StockTransaction::create([
                'product_id' => $this->product->id,
                'location_id' => $this->warehouse->id,
                'location_type' => Warehouse::class,
                'quantity' => 1,
                'direction' => 'inbound',
                'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
                'reference_type' => Invoice::class,
                'reference_id' => $this->invoice->id,
                'transaction_date' => now(),
                'status' => 'pending',
            ]);
        }

        // Test filtering with pagination
        $response = $this->get(route('returns.index', [
            'type' => 'customer_return',
            'per_page' => 5
        ]));
        
        $response->assertStatus(200);
        $response->assertViewHas('returns');
        
        $returns = $response->viewData('returns');
        $this->assertEquals(5, $returns->count()); // Per page
        $this->assertEquals(15, $returns->total()); // Total filtered records
    }
} 