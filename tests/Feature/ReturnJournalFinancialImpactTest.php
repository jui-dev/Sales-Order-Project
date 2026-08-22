<?php

namespace Tests\Feature;

use App\Accounting\AccountRole;
use App\Accounting\LedgerService;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\InvoiceService;
use App\Services\ReportService;
use App\Services\ReturnJournalHandler;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a customer return reaches the financial statements, and when it does not.
 *
 * This file used to assert that a return entry sat in draft carrying no
 * financial effect until somebody approved and posted it by hand. That is no
 * longer how it works: an entry the system raises from a document a person has
 * already approved is posted when it is raised, because a second approval of
 * the system's own arithmetic adds no control and only holds the books behind
 * reality.
 *
 * The invariant those tests were really protecting - that an unposted entry has
 * no effect on any statement - is still true and still tested here, on the kind
 * of entry it now applies to: one a person typed.
 */
class ReturnJournalFinancialImpactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    /** @test */
    public function it_puts_a_customer_return_on_the_statements_as_soon_as_the_note_is_posted()
    {
        $ledger = app(LedgerService::class);

        [$invoice, $creditNote] = $this->saleAndReturn();

        $before = [
            'receivable'    => $ledger->balance(AccountRole::AccountsReceivable)->toDecimal(),
            'salesReturns'  => $ledger->balance(AccountRole::SalesReturns)->toDecimal(),
        ];

        $entry = app(ReturnJournalHandler::class)->createCustomerReturnJournal($creditNote);

        $this->assertEquals(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertTrue($entry->isBalanced());

        // 2 units at 50 were sold and 1 came back. Gross revenue stays at 100
        // with the 50 shown against it in sales returns, rather than one
        // silently eroding the other, and the receivable falls to 50.
        $this->assertSame('100.00', $ledger->balance(AccountRole::SalesRevenue)->negated()->toDecimal());
        $this->assertSame('50.00', $ledger->balance(AccountRole::SalesReturns)->toDecimal());
        $this->assertSame('50.00', $ledger->balance(AccountRole::AccountsReceivable)->toDecimal());

        $this->assertNotSame($before['salesReturns'], $ledger->balance(AccountRole::SalesReturns)->toDecimal());
        $this->assertNotSame($before['receivable'], $ledger->balance(AccountRole::AccountsReceivable)->toDecimal());
    }

    /** @test */
    public function it_nets_the_return_out_of_the_income_statement()
    {
        [, $creditNote] = $this->saleAndReturn();

        app(ReturnJournalHandler::class)->createCustomerReturnJournal($creditNote);

        $statement = app(ReportService::class)->generateIncomeStatementReport();

        // Gross revenue of 100 less a 50 return is 50. Sales Returns is
        // contra-revenue, so it subtracts by its sign rather than by any
        // special case in the report.
        $this->assertEquals(50.0, round($statement['totalRevenue'], 2));

        $this->assertTrue(app(LedgerService::class)->trialBalanceTotals()['balanced']);
    }

    /** @test */
    public function it_validates_reverse_logic_for_customer_return_journal()
    {
        [, $creditNote] = $this->saleAndReturn();

        $handler = app(ReturnJournalHandler::class);
        $entry = $handler->createCustomerReturnJournal($creditNote);

        $this->assertTrue($handler->validateReverseLogic($entry));

        // A balanced entry against the wrong accounts is the failure mode
        // worth catching: every arithmetic check the ledger makes still passes.
        $wrongAccounts = JournalEntry::create([
            'formatted_id' => 'JE-TEST-INVALID',
            'entry_date'   => now(),
            'description'  => 'Invalid customer return entry',
            'status'       => JournalEntry::STATUS_DRAFT,
            'origin'       => JournalEntry::ORIGIN_MANUAL,
            'source_type'  => CreditNote::class,
            'source_id'    => $creditNote->id,
        ]);

        $wrongAccounts->lines()->create([
            'account_id' => Account::where('code', '1000')->first()->id, // Cash
            'debit'      => 50,
            'credit'     => 0,
        ]);

        $wrongAccounts->lines()->create([
            'account_id' => Account::where('code', '2100')->first()->id, // Sales Tax Payable
            'debit'      => 0,
            'credit'     => 50,
        ]);

        $this->assertFalse($handler->validateReverseLogic($wrongAccounts->fresh()));
    }

    /** @test */
    public function it_ensures_unposted_manual_entries_do_not_affect_financial_statements()
    {
        $ledger = app(LedgerService::class);
        $accounting = app(AccountingService::class);

        $before = $ledger->balance(AccountRole::Cash)->toDecimal();

        // A manual entry is the one kind that still takes the review path, and
        // it carries no weight at all until it has been through it.
        $entry = $accounting->post([
            ['account_code' => '1000', 'debit' => 500, 'credit' => 0],
            ['account_code' => '2100', 'debit' => 0, 'credit' => 500],
        ], null, 'Manual adjustment awaiting review');

        $this->assertEquals(JournalEntry::STATUS_DRAFT, $entry->status);
        $this->assertEquals(JournalEntry::ORIGIN_MANUAL, $entry->origin);
        $this->assertSame($before, $ledger->balance(AccountRole::Cash)->toDecimal());

        // Approval is review only - it moves nothing.
        $accounting->approveEntry($entry);
        $this->assertSame($before, $ledger->balance(AccountRole::Cash)->toDecimal());

        // Posting is the single moment the entry reaches the books.
        $accounting->postJournalEntry($entry->fresh());
        $this->assertSame('500.00', $ledger->balance(AccountRole::Cash)->toDecimal());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A completed sale of 2 units at 50, with 1 returned.
     *
     * @return array{0: Invoice, 1: CreditNote}
     */
    private function saleAndReturn(): array
    {
        $customer  = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create([
            'purchase_price' => 20,
            'selling_price'  => 50,
        ]);

        $order = Order::create([
            'customer_id'               => $customer->id,
            'status'                    => 'completed',
            'order_date'                => now(),
            'total_amount'              => 100,
            'fulfillment_location_id'   => null,
            'fulfillment_location_type' => null,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 50,
            'subtotal'   => 100,
        ]);

        $invoice = app(InvoiceService::class)->generateFromOrder($order);

        $return = StockTransaction::create([
            'product_id'       => $product->id,
            'location_id'      => $warehouse->id,
            'location_type'    => Warehouse::class,
            'quantity'         => 1,
            'direction'        => 'inbound',
            'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
            'reference_type'   => Invoice::class,
            'reference_id'     => $invoice->id,
            'transaction_date' => now(),
            'status'           => 'pending',
        ]);

        $approved = app(ReturnService::class)->approveReturn($return);

        $creditNote = CreditNote::where('return_transaction_id', $approved->id)->firstOrFail();

        return [$invoice, $creditNote];
    }
}
