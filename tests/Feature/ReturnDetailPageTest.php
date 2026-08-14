<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SupplierBill;
use App\Models\Supply;
use App\Models\Vendor;
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
 * The return detail page branches heavily on return type and status - the
 * movement it draws, the workflow rail, which note it links, which actions it
 * offers. These render it down each branch so a per-type regression shows up
 * as a failing test rather than a 500 in front of the user.
 */
class ReturnDetailPageTest extends TestCase
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
            'sku' => 'TEST001',
            'purchase_price' => 50.00,
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->retailer = Retailer::factory()->create(['name' => 'Main Retailer']);
    }

    private function customerReturn(int $quantity = 2): StockTransaction
    {
        $customer = Customer::factory()->create(['name' => 'Alice Johnson']);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        return app(ReturnService::class)->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'return_reason' => 'defective_product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);
    }

    private function retailerReturn(int $quantity = 5): StockTransaction
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

        ProductStock::create([
            'product_id' => $this->product->id,
            'location_id' => $this->retailer->id,
            'location_type' => Retailer::class,
            'quantity' => 20,
        ]);

        return app(ReturnService::class)->createRetailerReturn([
            'stock_transfer_id' => $transfer->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'return_reason' => 'excess_inventory',
            'return_date' => now(),
        ]);
    }

    /** @test */
    public function it_renders_a_pending_customer_return()
    {
        $return = $this->customerReturn();

        $response = $this->get(route('returns.show', $return));

        $response->assertOk();
        $response->assertSee('Customer Return');
        $response->assertSee('The Movement');
        // Pending: the page must not claim the stock has moved.
        $response->assertSee('Not yet applied');
        $response->assertSee('Approve Return');
        $response->assertSee('Reject');
    }

    /** @test */
    public function it_renders_an_approved_customer_return_with_its_credit_note()
    {
        $return = $this->customerReturn();
        app(ReturnService::class)->approveReturn($return);

        $response = $this->get(route('returns.show', $return->fresh()));

        $response->assertOk();
        $response->assertSee('Applied');
        $response->assertSee('Mark as Completed');
        // Approved returns offer neither approval nor rejection any more. Match
        // on the form targets, since "Approve Return" is also a rail stage name.
        $response->assertDontSee(route('returns.approve', $return), false);
        $response->assertDontSee(route('returns.reject', $return), false);
    }

    /** @test */
    public function it_renders_a_pending_vendor_return()
    {
        // Mirrors return 48 in the live data: outbound, warehouse to vendor.
        $vendor = Vendor::factory()->create(['name' => 'Global Tech Supplies']);
        $supply = Supply::factory()->create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $grn = Grn::factory()->create([
            'supply_id' => $supply->id,
            'status' => 'posted',
        ]);
        $bill = SupplierBill::factory()->create([
            'vendor_id' => $vendor->id,
            'grn_id' => $grn->id,
            'status' => 'posted',
        ]);

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);

        $response = $this->get(route('returns.show', $return));

        $response->assertOk();
        $response->assertSee('Vendor Return');
        $response->assertSee('Global Tech Supplies');
        // A vendor is outside our inventory, so only one ledger line is drawn.
        $response->assertSee('Leaves our inventory');
    }

    /** @test */
    public function it_renders_an_issued_retailer_return()
    {
        $return = $this->retailerReturn();

        $response = $this->get(route('returns.show', $return));

        $response->assertOk();
        $response->assertSee('Retailer Return');
        $response->assertSee('Approve &amp; Move Stock', false);
        // Retailer returns have no financial side at all. The sidebar carries a
        // Credit Notes link, so this checks the return itself links to no note.
        $response->assertSee('Internal stock move');
        $response->assertDontSee('credit-notes/', false);
        $response->assertDontSee('debit-notes/', false);
    }

    /** @test */
    public function it_renders_an_approved_retailer_return_without_a_complete_action()
    {
        $return = $this->retailerReturn();
        app(ReturnService::class)->approveReturn($return);

        $response = $this->get(route('returns.show', $return->fresh()));

        $response->assertOk();
        // The stock move is the whole job, so there is nothing left to complete.
        $response->assertDontSee('Mark as Completed');
        $response->assertSee('there is no journal entry and nothing further to do');
    }

    /** @test */
    public function it_renders_a_rejected_return_with_its_reason()
    {
        $return = $this->customerReturn();
        app(ReturnService::class)->rejectReturn($return, 'Outside the returns window');

        $response = $this->get(route('returns.show', $return->fresh()));

        $response->assertOk();
        $response->assertSee('Outside the returns window');
        $response->assertSee('no stock moved');
        $response->assertDontSee(route('returns.approve', $return), false);
    }

    /** @test */
    public function it_renders_a_return_whose_source_document_has_gone_missing()
    {
        // Deliberately dangling: reference_id points at nothing. The page has to
        // degrade rather than fatal on a null relation.
        $return = StockTransaction::create([
            'product_id' => $this->product->id,
            'location_id' => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity' => 1,
            'direction' => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type' => Invoice::class,
            'reference_id' => 999999,
            'transaction_date' => now(),
            'status' => StockTransaction::STATUS_PENDING,
        ]);

        $response = $this->get(route('returns.show', $return));

        $response->assertOk();
        $response->assertSee('Not available');
    }
}
