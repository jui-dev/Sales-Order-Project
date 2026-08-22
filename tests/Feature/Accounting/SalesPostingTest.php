<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The sales side: revenue, tax, discount and settlement.
 */
class SalesPostingTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;
    private Customer $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->ledger   = app(LedgerService::class);
        $this->customer = Customer::factory()->create();
        $this->order    = Order::factory()->create(['customer_id' => $this->customer->id]);
    }

    public function test_tax_collected_is_a_liability_and_not_revenue(): void
    {
        // 1000 of goods, 150 of tax, no discount.
        $this->invoice(subtotal: 1000, tax: 150, discount: 0, total: 1150);

        // Revenue is the goods alone. The old entry credited the whole 1150 to
        // revenue, overstating it by exactly the tax charged.
        $this->assertBalance(AccountRole::SalesRevenue, '-1000.00');
        $this->assertBalance(AccountRole::SalesTaxPayable, '-150.00');

        // The customer still owes the full amount including the tax.
        $this->assertBalance(AccountRole::AccountsReceivable, '1150.00');

        $this->assertTrialBalances();
    }

    public function test_a_discount_reduces_revenue_through_a_contra_account(): void
    {
        $this->invoice(subtotal: 1000, tax: 0, discount: 100, total: 900);

        // Gross sales stay visible at 1000 with the discount shown against
        // them, rather than one silently eroding the other.
        $this->assertBalance(AccountRole::SalesRevenue, '-1000.00');
        $this->assertBalance(AccountRole::SalesDiscount, '100.00');
        $this->assertBalance(AccountRole::AccountsReceivable, '900.00');

        $this->assertTrialBalances();
    }

    public function test_an_invoice_whose_own_figures_disagree_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not add up');

        // 1000 + 150 - 0 is 1150, not 1200. The entry would still balance -
        // the receivable is taken from the total either way - so the error
        // would never show on the trial balance.
        $this->invoice(subtotal: 1000, tax: 150, discount: 0, total: 1200);
    }

    public function test_payment_settles_the_receivable_against_the_customer(): void
    {
        $invoice = $this->invoice(subtotal: 1000, tax: 0, discount: 0, total: 1000);

        Payment::create([
            'invoice_id'   => $invoice->id,
            'amount'       => 400,
            'method'       => 'cash',
            'payment_date' => now(),
            'status'       => 'completed',
        ]);

        $this->assertBalance(AccountRole::Cash, '400.00');
        $this->assertBalance(AccountRole::AccountsReceivable, '600.00');

        // What this customer still owes is a grouping of the control account,
        // not a figure rebuilt from the invoices and hoped to agree.
        $byCustomer = $this->ledger->partyBalances(AccountRole::AccountsReceivable);
        $key = $this->customer->getMorphClass() . ':' . $this->customer->id;

        $this->assertSame('600.00', $byCustomer[$key]['balance']->toDecimal());

        $this->assertTrialBalances();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function invoice(float $subtotal, float $tax, float $discount, float $total): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-' . fake()->unique()->numberBetween(1000, 9999),
            'order_id'       => $this->order->id,
            'customer_id'    => $this->customer->id,
            'invoice_date'   => now(),
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'discount'       => $discount,
            'total'          => $total,
            'payment_status' => 'unpaid',
        ]);
    }

    private function assertBalance(AccountRole $role, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->ledger->balance($role)->toDecimal(),
            sprintf('%s (%s) is not where it should be.', $role->label(), $role->code()),
        );
    }

    private function assertTrialBalances(): void
    {
        $totals = $this->ledger->trialBalanceTotals();

        $this->assertTrue($totals['balanced'], 'Trial balance is out by ' . $totals['difference']->toDecimal());
    }
}
