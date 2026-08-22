<?php

namespace Tests\Feature\Accounting;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CreditNoteService;
use App\Services\ReturnService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two screens that report profit, rendered over real posted documents.
 *
 * ProfitReportingTest pins the arithmetic; this pins the pages, which is where
 * the original complaint was made: a product that had been sent back still read
 * as though it had earned. Ten sold, four returned - so 500 of the sale is
 * reversed and 510 of profit is left.
 */
class ProfitPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function both_profit_pages_render_a_sale_net_of_its_return(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs($this->adminUser());

        $warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Widget', 'purchase_price' => 40.00]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'order_date' => now()->toDateString(),
            'fulfillment_location_id' => $warehouse->id,
            'fulfillment_location_type' => Warehouse::class,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'location_id' => $warehouse->id, 'location_type' => Warehouse::class,
            'quantity' => 10, 'unit_price' => 125.00, 'unit_cost' => 40.00, 'subtotal' => 1250.00,
        ]);
        ProductStock::create([
            'product_id' => $product->id, 'location_id' => $warehouse->id,
            'location_type' => Warehouse::class, 'quantity' => 100,
        ]);
        $list = PickingList::create([
            'reference_type' => Order::class, 'reference_id' => $order->id,
            'picking_number' => 'PL-1', 'from_location_id' => $warehouse->id,
            'from_location_type' => Warehouse::class, 'status' => 'pending', 'picking_date' => now(),
        ]);
        PickingListItem::create([
            'picking_list_id' => $list->id, 'product_id' => $product->id,
            'quantity_requested' => 10, 'quantity_picked' => 10,
        ]);
        $list->update(['status' => 'completed']);

        $invoice = $order->fresh()->invoice()->firstOrFail();

        $return = app(ReturnService::class)->createCustomerReturn([
            'invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 4,
            'return_reason' => 'defective_product', 'return_location_type' => 'warehouse',
            'return_location_id' => $warehouse->id, 'return_date' => now(),
        ]);
        app(ReturnService::class)->approveReturn($return);
        app(CreditNoteService::class)->postCreditNote(
            CreditNote::where('return_transaction_id', $return->id)->firstOrFail()
        );

        $report = $this->get('/reports/daily-profit');
        $report->assertOk();
        $report->assertSee('Returns');
        $report->assertSee('Widget');
        // 4 x 125 came back, and is shown against gross sales rather than
        // quietly netted out of them.
        $report->assertSee('500.00');
        // 6 x (125 - 40) is what is actually left.
        $report->assertSee('510.00');
        $report->assertSee('Main Warehouse');

        $products = $this->get('/products');
        $products->assertOk();
        // Two columns answering two different questions: what a unit would
        // earn at today's price, and what this product actually earned.
        $products->assertSee('Margin/unit');
        $products->assertSee('GP (MTD)');
        $products->assertSee('510.00');
    }
}
