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

class ReturnFormSubmissionTest extends TestCase
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
            'fulfillment_location_type' => 'App\Models\Warehouse',
            'fulfillment_location_id' => $this->warehouse->id,
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
    public function it_requires_items_field_for_return_submission()
    {
        $response = $this->post(route('returns.store'), [
            'return_type' => 'customer_return',
            'return_date' => now()->format('Y-m-d'),
            'notes' => 'Test return',
            // Missing items field
        ]);

        $response->assertSessionHasErrors(['items']);
        $response->assertSessionHasErrors(['items' => 'The items field is required.']);
    }

    /** @test */
    public function it_accepts_valid_items_array_for_customer_return()
    {
        $response = $this->post(route('returns.store'), [
            'return_type' => 'customer_return',
            'invoice_id' => $this->invoice->id,
            'return_location_type' => 'App\Models\Warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now()->format('Y-m-d'),
            'notes' => 'Test return',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1
                ]
            ]
        ]);

        $response->assertRedirect(route('returns.index'));
        $response->assertSessionHas('success');
        
        // Verify the return transaction was created
        $this->assertDatabaseHas('stock_transactions', [
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'invoice_id' => $this->invoice->id,
        ]);
    }

    /** @test */
    public function it_validates_items_array_structure()
    {
        $response = $this->post(route('returns.store'), [
            'return_type' => 'customer_return',
            'invoice_id' => $this->invoice->id,
            'return_location_type' => 'App\Models\Warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    // Missing quantity
                ]
            ]
        ]);

        $response->assertSessionHasErrors(['items.0.quantity' => 'The items.0.quantity field is required.']);
    }

    /** @test */
    public function it_validates_product_id_exists()
    {
        $response = $this->post(route('returns.store'), [
            'return_type' => 'customer_return',
            'invoice_id' => $this->invoice->id,
            'return_location_type' => 'App\Models\Warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => 99999, // Non-existent product
                    'quantity' => 1
                ]
            ]
        ]);

        $response->assertSessionHasErrors(['items.0.product_id' => 'The selected items.0.product_id is invalid.']);
    }

    /** @test */
    public function it_validates_quantity_is_positive_integer()
    {
        $response = $this->post(route('returns.store'), [
            'return_type' => 'customer_return',
            'invoice_id' => $this->invoice->id,
            'return_location_type' => 'App\Models\Warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 0 // Invalid quantity
                ]
            ]
        ]);

        $response->assertSessionHasErrors(['items.0.quantity' => 'The items.0.quantity must be at least 1.']);
    }
} 