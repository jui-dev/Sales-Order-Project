<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Warehouse;
use App\Models\StockTransaction;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CreditNoteTest extends TestCase
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
    public function it_can_generate_credit_note_for_customer_return()
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

        $creditNoteService = app(CreditNoteService::class);
        $creditNote = $creditNoteService->generateCreditNote($return);

        $this->assertInstanceOf(CreditNote::class, $creditNote);
        $this->assertEquals($this->invoice->id, $creditNote->invoice_id);
        $this->assertEquals($this->customer->id, $creditNote->customer_id);
        $this->assertEquals($return->id, $creditNote->return_transaction_id);
        $this->assertEquals('issued', $creditNote->status);
        $this->assertEquals(100.00, $creditNote->total_amount);
        $this->assertStringStartsWith('CN', $creditNote->credit_note_number);

        // Check credit note item
        $this->assertCount(1, $creditNote->items);
        $item = $creditNote->items->first();
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(100.00, $item->unit_price);
        $this->assertEquals(100.00, $item->total_amount);
    }

    /** @test */
    public function it_cannot_generate_credit_note_for_non_customer_return()
    {
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'outbound',
            'transaction_type' => StockTransaction::TYPE_VENDOR_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => $this->invoice->id,
            'transaction_date' => now(),
            'status' => 'approved',
        ]);

        $creditNoteService = app(CreditNoteService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit notes can only be generated for customer returns');
        
        $creditNoteService->generateCreditNote($return);
    }

    /** @test */
    public function it_cannot_generate_credit_note_for_pending_return()
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

        $creditNoteService = app(CreditNoteService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit notes can only be generated for approved returns');
        
        $creditNoteService->generateCreditNote($return);
    }

    /** @test */
    public function it_can_access_credit_notes_index_page()
    {
        $response = $this->get(route('credit-notes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('credit-notes.index');
        $response->assertViewHas('creditNotes');
        $response->assertViewHas('statistics');
    }

    /** @test */
    public function it_can_filter_credit_notes()
    {
        $response = $this->get(route('credit-notes.index', [
            'customer_id' => $this->customer->id,
            'status' => 'issued',
            'search' => 'CN'
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('credit-notes.index');
    }

    /** @test */
    public function it_can_cancel_credit_note()
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

        $creditNote = CreditNote::create([
            'invoice_id' => $this->invoice->id,
            'customer_id' => $this->customer->id,
            'return_transaction_id' => $return->id,
            'credit_note_number' => 'CN2025010001',
            'total_amount' => 100.00,
            'status' => 'issued',
            'issue_date' => now(),
        ]);

        $creditNoteService = app(CreditNoteService::class);
        $cancelledCreditNote = $creditNoteService->cancelCreditNote($creditNote);

        $this->assertEquals('cancelled', $cancelledCreditNote->status);
        $this->assertStringContains('Cancelled on', $cancelledCreditNote->notes);
    }

    /** @test */
    public function it_cannot_cancel_non_issued_credit_note()
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

        $creditNote = CreditNote::create([
            'invoice_id' => $this->invoice->id,
            'customer_id' => $this->customer->id,
            'return_transaction_id' => $return->id,
            'credit_note_number' => 'CN2025010001',
            'total_amount' => 100.00,
            'status' => 'pending',
            'issue_date' => now(),
        ]);

        $creditNoteService = app(CreditNoteService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only issued credit notes can be cancelled');
        
        $creditNoteService->cancelCreditNote($creditNote);
    }
} 