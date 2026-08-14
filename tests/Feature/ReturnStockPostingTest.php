<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Retailer;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Returns used to apply their stock movement on creation and then again on
 * every status change, so a single return could move stock three or four
 * times. These tests pin the rule down: a return moves stock once, at
 * approval, and never again.
 */
class ReturnStockPostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;
    protected Retailer $retailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'purchase_price' => 50.00,
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->retailer = Retailer::factory()->create(['name' => 'Main Retailer']);
    }

    private function stockAt(string $locationType, int $locationId): int
    {
        return (int) ProductStock::where('product_id', $this->product->id)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->value('quantity');
    }

    private function seedStock(string $locationType, int $locationId, int $quantity): void
    {
        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $locationId,
            'location_type' => $locationType,
            'quantity' => $quantity,
        ]);
    }

    /** @test */
    public function customer_return_posts_stock_once_at_approval()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->seedStock(Warehouse::class, $this->warehouse->id, 100);

        $returnService = app(ReturnService::class);

        $return = $returnService->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);

        // Pending: nothing has come back into the warehouse yet.
        $this->assertEquals(100, $this->stockAt(Warehouse::class, $this->warehouse->id));
        $this->assertNull($return->fresh()->stock_posted_at);

        $returnService->approveReturn($return);

        $this->assertEquals(104, $this->stockAt(Warehouse::class, $this->warehouse->id));
        $this->assertNotNull($return->fresh()->stock_posted_at);

        $returnService->completeReturn($return->fresh());
        $this->assertEquals(104, $this->stockAt(Warehouse::class, $this->warehouse->id));

        // The guard used to be a 1-hour cache key, so a late re-post slipped
        // through. It is recorded on the row now, so this stays a no-op.
        $return->fresh()->updateProductStock();
        $this->assertEquals(104, $this->stockAt(Warehouse::class, $this->warehouse->id));
    }

    /** @test */
    public function retailer_return_moves_stock_once_from_retailer_to_warehouse()
    {
        $transfer = StockTransfer::factory()->create([
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => Retailer::class,
            'to_location_id' => $this->retailer->id,
            'status' => 'completed',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);

        $this->seedStock(Warehouse::class, $this->warehouse->id, 30);
        $this->seedStock(Retailer::class, $this->retailer->id, 20);

        $returnService = app(ReturnService::class);

        $return = $returnService->createRetailerReturn([
            'stock_transfer_id' => $transfer->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'return_reason' => 'Excess inventory',
            'return_date' => now(),
        ]);

        // Issued, not yet approved: both sides untouched.
        $this->assertEquals(StockTransaction::STATUS_ISSUED, $return->status);
        $this->assertEquals(30, $this->stockAt(Warehouse::class, $this->warehouse->id));
        $this->assertEquals(20, $this->stockAt(Retailer::class, $this->retailer->id));

        $returnService->approveReturn($return);

        // Exactly one application on each side: 20-5 and 30+5.
        $this->assertEquals(15, $this->stockAt(Retailer::class, $this->retailer->id));
        $this->assertEquals(35, $this->stockAt(Warehouse::class, $this->warehouse->id));

        // And it stays that way however many times posting is retriggered.
        $return->fresh()->updateProductStock();
        $this->assertEquals(15, $this->stockAt(Retailer::class, $this->retailer->id));
        $this->assertEquals(35, $this->stockAt(Warehouse::class, $this->warehouse->id));
    }

    /** @test */
    public function retailer_return_cannot_drive_retailer_stock_negative()
    {
        $transfer = StockTransfer::factory()->create([
            'from_location_type' => Warehouse::class,
            'from_location_id' => $this->warehouse->id,
            'to_location_type' => Retailer::class,
            'to_location_id' => $this->retailer->id,
            'status' => 'completed',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);

        // The retailer has since sold most of what it was sent.
        $this->seedStock(Retailer::class, $this->retailer->id, 3);

        $returnService = app(ReturnService::class);

        $return = $returnService->createRetailerReturn([
            'stock_transfer_id' => $transfer->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'return_reason' => 'Excess inventory',
            'return_date' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $returnService->approveReturn($return);
        } finally {
            $this->assertEquals(3, $this->stockAt(Retailer::class, $this->retailer->id));
        }
    }

    /** @test */
    public function rejected_return_never_posts_stock()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->seedStock(Warehouse::class, $this->warehouse->id, 100);

        $returnService = app(ReturnService::class);

        $return = $returnService->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'return_reason' => 'Defective product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);

        $returnService->rejectReturn($return, 'Outside the returns window');

        $return->refresh();

        $this->assertEquals(StockTransaction::STATUS_REJECTED, $return->status);
        $this->assertEquals($this->user->id, $return->rejected_by);
        $this->assertNotNull($return->rejected_at);
        $this->assertEquals('Outside the returns window', $return->rejection_reason);
        $this->assertNull($return->stock_posted_at);

        $this->assertEquals(100, $this->stockAt(Warehouse::class, $this->warehouse->id));

        // A rejected return must not eat into what can still be returned.
        $validation = $returnService->validateReturnQuantity(
            StockTransaction::TYPE_CUSTOMER_RETURN,
            $invoice->id,
            $this->product->id,
            10
        );
        $this->assertTrue($validation['valid'], implode(' ', $validation['errors']));
    }

    /** @test */
    public function cancelling_a_return_preserves_its_return_reason()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $return = app(ReturnService::class)->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'Damaged in transit',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);

        $return->cancel($this->user->id, 'Customer changed their mind');

        $return->refresh();

        // Cancellation details used to be json_encode'd over the top of the
        // return reason, which lives in `notes`.
        $this->assertEquals('Damaged in transit', $return->return_reason);
        $this->assertEquals('Customer changed their mind', $return->cancellation_notes);
        $this->assertEquals($this->user->id, $return->cancelled_by);
    }
}
