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

class ReturnStatusChangeTest extends TestCase
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
    public function it_can_approve_pending_return()
    {
        $return = StockTransaction::create([
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

        $response = $this->post(route('returns.approve', $return));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $return->refresh();
        $this->assertEquals('approved', $return->status);
    }

    /** @test */
    public function it_can_complete_approved_return()
    {
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'approved',
        ]);

        $response = $this->post(route('returns.complete', $return));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $return->refresh();
        $this->assertEquals('completed', $return->status);
    }

    /** @test */
    public function it_cannot_approve_completed_return()
    {
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'completed',
        ]);

        $response = $this->post(route('returns.approve', $return));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $return->refresh();
        $this->assertEquals('completed', $return->status);
    }

    /** @test */
    public function it_cannot_complete_pending_return()
    {
        $return = StockTransaction::create([
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

        $response = $this->post(route('returns.complete', $return));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $return->refresh();
        $this->assertEquals('pending', $return->status);
    }

    /** @test */
    public function it_cannot_approve_non_return_transaction()
    {
        $transaction = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_STOCK_IN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $response = $this->post(route('returns.approve', $transaction));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $transaction->refresh();
        $this->assertEquals('pending', $transaction->status);
    }
} 