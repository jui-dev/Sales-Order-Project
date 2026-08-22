<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\Exceptions\ClosedPeriod;
use App\Accounting\LedgerService;
use App\Accounting\PeriodCloseService;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Order;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;
    private PeriodCloseService $periods;
    private Customer $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->ledger   = app(LedgerService::class);
        $this->periods  = app(PeriodCloseService::class);
        $this->customer = Customer::factory()->create();
        $this->order    = Order::factory()->create(['customer_id' => $this->customer->id]);
    }

    public function test_closing_moves_the_result_into_retained_earnings(): void
    {
        $this->sell(1000, on: '2026-03-10');

        $period = FiscalPeriod::findOrCreateFor(Carbon::parse('2026-03-10'));
        $this->periods->close($period);

        // Revenue has been emptied out and now starts the next period at nil.
        $this->assertSame('0.00', $this->ledger->balance(AccountRole::SalesRevenue)->toDecimal());

        // Retained earnings is a real posted balance, not a figure the balance
        // sheet works out on every render to make the equation hold.
        $this->assertSame('-1000.00', $this->ledger->balance(AccountRole::RetainedEarnings)->toDecimal());

        $this->assertTrue($this->ledger->trialBalanceTotals()['balanced']);
    }

    public function test_the_income_statement_for_a_closed_period_still_shows_its_revenue(): void
    {
        $this->sell(1000, on: '2026-03-10');
        $this->periods->close(FiscalPeriod::findOrCreateFor(Carbon::parse('2026-03-10')));

        // The closing entry is dated inside the period it closes, so a reader
        // that counted it would report nil profit for every closed period.
        $revenue = $this->ledger
            ->excludingClosingEntries()
            ->balance(AccountRole::SalesRevenue, '2026-03-31', '2026-03-01');

        $this->assertSame('-1000.00', $revenue->toDecimal());
    }

    public function test_a_closed_period_refuses_further_postings(): void
    {
        $this->sell(1000, on: '2026-03-10');
        $this->periods->close(FiscalPeriod::findOrCreateFor(Carbon::parse('2026-03-10')));

        $this->expectException(ClosedPeriod::class);

        // Backdating into a period that has been reported on would silently
        // restate a statement somebody has already acted on.
        $this->sell(500, on: '2026-03-20');
    }

    public function test_a_later_period_is_unaffected(): void
    {
        $this->sell(1000, on: '2026-03-10');
        $this->periods->close(FiscalPeriod::findOrCreateFor(Carbon::parse('2026-03-10')));

        $this->sell(400, on: '2026-04-05');

        // April's revenue starts from zero; March's result sits in equity.
        $this->assertSame('-400.00', $this->ledger->balance(AccountRole::SalesRevenue)->toDecimal());
        $this->assertSame('-1000.00', $this->ledger->balance(AccountRole::RetainedEarnings)->toDecimal());
        $this->assertTrue($this->ledger->trialBalanceTotals()['balanced']);
    }

    public function test_reopening_reverses_the_closing_entry_rather_than_deleting_it(): void
    {
        $this->sell(1000, on: '2026-03-10');
        $period = FiscalPeriod::findOrCreateFor(Carbon::parse('2026-03-10'));

        $this->periods->close($period);
        $closingEntry = $period->fresh()->closingEntry;

        $this->periods->reopen($period->fresh());

        // The closing entry is still on the books, with a reversal against it.
        $this->assertNotNull($closingEntry->fresh(), 'A posted entry is never deleted.');
        $this->assertSame(1, $closingEntry->reverseJournals()->count());

        // Revenue is back where it was and retained earnings is empty again.
        $this->assertSame('-1000.00', $this->ledger->balance(AccountRole::SalesRevenue)->toDecimal());
        $this->assertSame('0.00', $this->ledger->balance(AccountRole::RetainedEarnings)->toDecimal());
        $this->assertTrue($this->ledger->trialBalanceTotals()['balanced']);
    }

    private function sell(float $amount, string $on): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-' . fake()->unique()->numberBetween(10000, 99999),
            'order_id'       => $this->order->id,
            'customer_id'    => $this->customer->id,
            'invoice_date'   => $on,
            'subtotal'       => $amount,
            'tax'            => 0,
            'discount'       => 0,
            'total'          => $amount,
            'payment_status' => 'unpaid',
        ]);
    }
}
