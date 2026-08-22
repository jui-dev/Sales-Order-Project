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
use App\Services\ProductService;
use App\Services\ReportService;
use App\Services\ReturnService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Profit is what the ledger says it is.
 *
 * The daily profit report used to sum order lines, so a sale that had been
 * returned and credited went on reporting its full profit for ever: the credit
 * note hangs off the invoice, not the order, and nothing on the order side
 * could see it. These tests pin the two things that were wrong - a return has
 * to reach the profit figure, and only posted documents may contribute to it -
 * and the agreement with the income statement that makes both checkable.
 */
class ProfitReportingTest extends TestCase
{
    use RefreshDatabase;

    private const UNIT_PRICE = 125.00;
    private const UNIT_COST = 40.00;
    private const SOLD = 10;

    private ReportService $reports;
    private Product $product;
    private Warehouse $warehouse;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // The one test in this suite whose window is relative to the clock:
        // rangeStart() and rangeEnd() are evaluated when the report is asked
        // for, while the entries were dated when they were written. Pinning the
        // clock is what makes the exact figures below a property of the posting
        // rules rather than of when the suite happened to run. Mid-month on
        // purpose, so nothing here sits on a period boundary.
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->reports   = app(ReportService::class);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->customer  = Customer::factory()->create();
        $this->product   = Product::factory()->create([
            'name'           => 'Widget',
            'sku'            => 'WIDGET-1',
            'purchase_price' => self::UNIT_COST,
        ]);
    }

    /** @test */
    public function a_sale_earns_the_margin_between_its_price_and_its_cost(): void
    {
        $this->sell();

        $summary = $this->report()['summary'];

        $this->assertSame(1250.00, $summary['gross_revenue']);
        $this->assertSame(0.0, $summary['total_returns']);
        $this->assertSame(400.00, $summary['total_cost']);
        $this->assertSame(850.00, $summary['total_profit']);
    }

    /**
     * The bug, stated as a test.
     *
     * @test
     */
    public function returning_everything_that_was_sold_leaves_no_profit(): void
    {
        $invoice = $this->sell();

        $this->assertSame(850.00, $this->report()['summary']['total_profit']);

        $this->returnGoods($invoice, self::SOLD);

        $summary = $this->report()['summary'];

        // Gross sales stay visible; the return is shown against them rather
        // than quietly eroding them.
        $this->assertSame(1250.00, $summary['gross_revenue']);
        $this->assertSame(1250.00, $summary['total_returns']);
        $this->assertSame(0.0, $summary['total_revenue']);

        // The cost of the goods comes back out with them, so the profit is not
        // merely reduced - there is none, because nothing was sold in the end.
        $this->assertSame(0.0, $summary['total_cost']);
        $this->assertSame(0.0, $summary['total_profit']);
    }

    /** @test */
    public function a_partial_return_reduces_profit_by_what_came_back(): void
    {
        $invoice = $this->sell();

        $this->returnGoods($invoice, 4);

        $summary = $this->report()['summary'];

        // 6 of 10 stay sold: 6 x (125 - 40).
        $this->assertSame(500.00, $summary['total_returns']);
        $this->assertSame(750.00, $summary['total_revenue']);
        $this->assertSame(240.00, $summary['total_cost']);
        $this->assertSame(510.00, $summary['total_profit']);
    }

    /**
     * The report and the income statement are two readings of one set of
     * entries, so they cannot be allowed to disagree. They used to be built
     * from different things entirely.
     *
     * @test
     */
    public function the_report_agrees_with_the_income_statement(): void
    {
        $invoice = $this->sell();
        $this->returnGoods($invoice, 4);

        $range = ['start_date' => $this->rangeStart(), 'end_date' => $this->rangeEnd()];

        $this->assertSame(
            round($this->reports->generateIncomeStatementReport($range)['grossProfit'], 2),
            $this->report()['summary']['total_profit'],
        );
    }

    /**
     * A profit report that counted orders booked profit the moment one was
     * typed, before anything shipped or was billed.
     *
     * @test
     */
    public function an_order_that_has_not_been_invoiced_is_not_yet_profit(): void
    {
        $order = $this->order();

        OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $this->product->id,
            'location_id'   => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity'      => self::SOLD,
            'unit_price'    => self::UNIT_PRICE,
            'unit_cost'     => self::UNIT_COST,
            'subtotal'      => self::UNIT_PRICE * self::SOLD,
        ]);

        $summary = $this->report()['summary'];

        // Nothing posted, so the blank summary is what comes back - its zeros
        // are ints, and the point is that they are zero either way.
        $this->assertEquals(0, $summary['total_revenue']);
        $this->assertEquals(0, $summary['total_profit']);
    }

    /** @test */
    public function the_detail_splits_profit_by_product_and_location(): void
    {
        $this->sell();

        $rows = $this->report()['dailyProfits'];

        $this->assertCount(1, $rows);
        $this->assertSame('Widget', $rows[0]['product_name']);
        $this->assertSame('warehouse', $rows[0]['location_type']);
        $this->assertSame('Main Warehouse', $rows[0]['location_name']);
        $this->assertSame(850.00, $rows[0]['profit']);

        // Everything the totals count is attributed to something.
        $this->assertSame(0.0, $this->report()['summary']['unattributed']);
    }

    // ------------------------------------------------------------------
    // Realised profit on the products listing
    // ------------------------------------------------------------------

    /** @test */
    public function realised_profit_per_product_is_net_of_returns(): void
    {
        $invoice = $this->sell();
        $this->returnGoods($invoice, 4);

        $profit = $this->reports->realisedProfitByProduct(
            [$this->product->id],
            $this->rangeStart(),
            $this->rangeEnd(),
        );

        $this->assertSame(750.00, $profit[$this->product->id]['revenue']);
        $this->assertSame(510.00, $profit[$this->product->id]['profit']);
    }

    /** @test */
    public function a_product_with_no_posted_sale_has_no_realised_profit(): void
    {
        $listed = app(ProductService::class)->getFilteredProducts([], 20)
            ->getCollection()
            ->firstWhere('id', $this->product->id);

        // Absent rather than zero: not having sold and having sold at cost are
        // different facts, and the page says so.
        $this->assertNull($listed->realised_profit);
    }

    /** @test */
    public function the_listing_shows_realised_profit_without_touching_the_catalogue_margin(): void
    {
        $invoice = $this->sell();
        $this->returnGoods($invoice, 4);

        $before = $this->product->fresh()->gross_profit;

        $listed = app(ProductService::class)->getFilteredProducts([], 20)
            ->getCollection()
            ->firstWhere('id', $this->product->id);

        $this->assertSame(510.00, $listed->realised_profit);

        // The catalogue margin is a property of the price list and has no
        // business moving because something was sold or sent back.
        $this->assertEquals($before, $this->product->fresh()->gross_profit);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Sell SOLD units: order, pick, ship. Completing the pick relieves cost,
     * closes the order and raises the invoice, which is what puts the revenue
     * on the books.
     */
    private function sell(): \App\Models\Invoice
    {
        $order = $this->order();

        OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $this->product->id,
            'location_id'   => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity'      => self::SOLD,
            'unit_price'    => self::UNIT_PRICE,
            'unit_cost'     => self::UNIT_COST,
            'subtotal'      => self::UNIT_PRICE * self::SOLD,
        ]);

        ProductStock::create([
            'product_id'    => $this->product->id,
            'location_id'   => $this->warehouse->id,
            'location_type' => Warehouse::class,
            'quantity'      => 100,
        ]);

        $list = PickingList::create([
            'reference_type'     => Order::class,
            'reference_id'       => $order->id,
            'picking_number'     => 'PL-' . $order->id,
            'from_location_id'   => $this->warehouse->id,
            'from_location_type' => Warehouse::class,
            'status'             => 'pending',
            'picking_date'       => now(),
        ]);

        PickingListItem::create([
            'picking_list_id'    => $list->id,
            'product_id'         => $this->product->id,
            'quantity_requested' => self::SOLD,
            'quantity_picked'    => self::SOLD,
        ]);

        $list->update(['status' => 'completed']);

        return $order->fresh()->invoice()->firstOrFail();
    }

    private function order(): Order
    {
        return Order::factory()->create([
            'customer_id'                => $this->customer->id,
            'status'                     => 'confirmed',
            'order_date'                 => now()->toDateString(),
            'fulfillment_location_id'    => $this->warehouse->id,
            'fulfillment_location_type'  => Warehouse::class,
        ]);
    }

    /**
     * Send goods back and post the credit note, which is the point at which a
     * return reaches the books.
     */
    private function returnGoods(\App\Models\Invoice $invoice, int $quantity): void
    {
        $return = app(ReturnService::class)->createCustomerReturn([
            'invoice_id'           => $invoice->id,
            'product_id'           => $this->product->id,
            'quantity'             => $quantity,
            'return_reason'        => 'defective_product',
            'return_location_type' => 'warehouse',
            'return_location_id'   => $this->warehouse->id,
            'return_date'          => now(),
        ]);

        app(ReturnService::class)->approveReturn($return);

        app(CreditNoteService::class)->postCreditNote(
            CreditNote::where('return_transaction_id', $return->id)->firstOrFail()
        );
    }

    private function report(): array
    {
        return $this->reports->generateDailyProfitReport([
            'start_date' => $this->rangeStart(),
            'end_date'   => $this->rangeEnd(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function rangeStart(): string
    {
        return now()->subDay()->toDateString();
    }

    private function rangeEnd(): string
    {
        return now()->addDay()->toDateString();
    }
}
