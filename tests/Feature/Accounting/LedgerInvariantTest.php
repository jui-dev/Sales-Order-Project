<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Accounting\PostingEngine;
use App\Accounting\Reconciliation\ReconciliationService;
use App\Models\Customer;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\GrnService;
use App\Services\SupplierBillService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The things that must be true of the books no matter what happened to them.
 *
 * Rather than asserting one arrangement of documents, this throws a randomised
 * pile of them at the ledger and checks the invariants afterwards. It is the
 * cheapest way to catch a posting rule that only balances for the case someone
 * happened to write a test for.
 */
class LedgerInvariantTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->ledger = app(LedgerService::class);
    }

    public function test_the_books_hold_after_a_pile_of_random_documents(): void
    {
        $vendors    = Vendor::factory()->count(3)->create();
        $warehouses = Warehouse::factory()->count(2)->create();
        $products   = Product::factory()->count(4)->create(['purchase_price' => 15]);
        $customers  = Customer::factory()->count(3)->create();

        mt_srand(20260822); // Deterministic: a failure has to be reproducible.

        for ($i = 0; $i < 12; $i++) {
            $this->randomPurchase(
                $vendors->random(),
                $warehouses->random(),
                $products->random(),
                quantity: mt_rand(1, 20),
                unitCost: mt_rand(500, 4000) / 100,
                payIt: mt_rand(0, 1) === 1,
                billIt: mt_rand(0, 4) > 0,
            );

            $this->randomSale(
                $customers->random(),
                subtotal: mt_rand(1000, 50000) / 100,
                taxRate: [0, 0.05, 0.15][mt_rand(0, 2)],
                discount: mt_rand(0, 1) === 1 ? mt_rand(100, 900) / 100 : 0,
                payFraction: [0.0, 0.5, 1.0][mt_rand(0, 2)],
            );
        }

        $this->assertGreaterThan(10, JournalEntry::count(), 'The run should have posted plenty.');

        // 1. Every entry balances on its own.
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertTrue(
                $entry->isBalanced(),
                sprintf('%s (%s) does not balance.', $entry->formatted_id, $entry->rule_key ?? 'manual'),
            );
        }

        // 2. So does the ledger as a whole.
        $totals = $this->ledger->trialBalanceTotals();
        $this->assertTrue($totals['balanced'], 'Trial balance out by ' . $totals['difference']->toDecimal());

        // 3. And every control account still ties to the documents behind it.
        foreach (app(ReconciliationService::class)->run() as $check) {
            $this->assertTrue($check->passed, sprintf(
                '%s out by %s (ledger %s, expected %s).',
                $check->title,
                $check->difference->toDecimal(),
                $check->ledger->toDecimal(),
                $check->expected->toDecimal(),
            ));
        }
    }

    public function test_reversing_every_entry_empties_the_books(): void
    {
        $customer = Customer::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->randomSale($customer, subtotal: 100 + $i, taxRate: 0.1, discount: 0, payFraction: 0.5);
        }

        $engine = app(PostingEngine::class);

        foreach (JournalEntry::posted()->get() as $entry) {
            $engine->reverse($entry, now(), 'invariant check');
        }

        // A reversal is a mirror, so reversing everything leaves nothing behind
        // on any account - while every original entry is still on the books.
        foreach (AccountRole::cases() as $role) {
            $this->assertSame(
                '0.00',
                $this->ledger->balance($role)->toDecimal(),
                $role->label() . ' should be empty once every entry is reversed.',
            );
        }

        $this->assertTrue($this->ledger->trialBalanceTotals()['balanced']);
        $this->assertGreaterThan(0, JournalEntry::where('is_reverse', true)->count());
    }

    // ------------------------------------------------------------------
    // Document builders
    // ------------------------------------------------------------------

    private function randomPurchase(
        Vendor $vendor,
        Warehouse $warehouse,
        Product $product,
        int $quantity,
        float $unitCost,
        bool $payIt,
        bool $billIt,
    ): void {
        $supply = Supply::create([
            'vendor_id'    => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'completed',
            'supply_date'  => now(),
            'total_cost'   => round($quantity * $unitCost, 2),
        ]);

        SupplyItem::create([
            'supply_id'  => $supply->id,
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'unit_cost'  => $unitCost,
            'subtotal'   => round($quantity * $unitCost, 2),
        ]);

        $grn = Grn::create([
            'supply_id'     => $supply->id,
            'received_date' => now(),
            'status'        => 'draft',
        ]);

        app(GrnService::class)->transitionStatus($grn->id, 'posted');

        // Not every delivery has been billed yet - that is exactly what GR-IR
        // is there to hold.
        if (! $billIt) {
            return;
        }

        $bills = app(SupplierBillService::class);
        $bill = $bills->postSupplierBill($grn->fresh()->supplierBill);

        if ($payIt) {
            $bills->paySupplierBill($bill->fresh());
        }
    }

    private function randomSale(
        Customer $customer,
        float $subtotal,
        float $taxRate,
        float $discount,
        float $payFraction,
    ): void {
        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax - $discount, 2);

        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . fake()->unique()->numberBetween(100000, 999999),
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'invoice_date'   => now(),
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'discount'       => $discount,
            'total'          => $total,
            'payment_status' => 'unpaid',
        ]);

        if ($payFraction <= 0) {
            return;
        }

        Payment::create([
            'invoice_id'   => $invoice->id,
            'amount'       => round($total * $payFraction, 2),
            'method'       => 'cash',
            'payment_date' => now(),
            'status'       => 'completed',
        ]);
    }
}
