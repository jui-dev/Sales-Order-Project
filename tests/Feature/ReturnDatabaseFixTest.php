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

class ReturnDatabaseFixTest extends TestCase
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
    public function it_can_create_stock_transaction_with_invoice_reference()
    {
        // This test verifies that the database error is fixed
        $transaction = StockTransaction::create([
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

        $this->assertDatabaseHas('stock_transactions', [
            'id' => $transaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
        ]);

        // Test that the morphTo relationship works
        $transaction->load('reference');
        $this->assertNotNull($transaction->reference);
        $this->assertInstanceOf(Invoice::class, $transaction->reference);
        $this->assertEquals($this->invoice->id, $transaction->reference->id);
    }

    /** @test */
    public function it_can_access_invoice_through_accessor()
    {
        $transaction = StockTransaction::create([
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

        // Test the accessor method
        $this->assertNotNull($transaction->invoice);
        $this->assertInstanceOf(Invoice::class, $transaction->invoice);
        $this->assertEquals($this->invoice->id, $transaction->invoice->id);
    }

    /** @test */
    public function it_returns_null_for_wrong_reference_type()
    {
        $transaction = StockTransaction::create([
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

        // Test that supplierBill accessor returns null for invoice reference
        $this->assertNull($transaction->supplierBill);
    }
} 