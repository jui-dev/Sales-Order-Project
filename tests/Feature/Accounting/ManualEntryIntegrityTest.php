<?php

namespace Tests\Feature\Accounting;

use App\Accounting\AccountRole;
use App\Accounting\Exceptions\ClosedPeriod;
use App\Accounting\Exceptions\MissingDimension;
use App\Accounting\Exceptions\UnbalancedEntry;
use App\Accounting\PeriodCloseService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Warehouse;
use App\Services\JournalEntryService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * An entry a person typed is checked the same way one a rule raised is.
 *
 * JournalEntryService used to write journal_entries and journal_entry_lines
 * itself - the only writer outside app/Accounting - so the manual path was the
 * one path with no period guard, no exact balance check, no dimension
 * requirement and no refusal to post to a rollup account. Every test here fails
 * against that version.
 */
class ManualEntryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private JournalEntryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->service = app(JournalEntryService::class);
    }

    public function test_a_control_account_line_must_name_its_party(): void
    {
        $this->expectException(MissingDimension::class);

        $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::AccountsReceivable), 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    public function test_naming_the_party_lets_it_through_and_reaches_the_subsidiary_ledger(): void
    {
        $customer = Customer::factory()->create();

        $entry = $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                [
                    'account_id' => $this->accountId(AccountRole::AccountsReceivable),
                    'debit' => 100,
                    'credit' => 0,
                    'party' => 'customer:' . $customer->id,
                ],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $line = $entry->lines->firstWhere('party_id', $customer->id);

        $this->assertNotNull($line, 'The receivable line did not carry its customer.');
        $this->assertSame(Customer::class, $line->party_type);
    }

    public function test_an_inventory_line_must_name_its_location(): void
    {
        $this->expectException(MissingDimension::class);

        $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::Inventory), 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    public function test_an_inventory_line_naming_its_location_is_accepted(): void
    {
        $warehouse = Warehouse::factory()->create();

        $entry = $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                [
                    'account_id' => $this->accountId(AccountRole::Inventory),
                    'debit' => 100,
                    'credit' => 0,
                    'location' => 'warehouse:' . $warehouse->id,
                ],
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $line = $entry->lines->firstWhere('location_id', $warehouse->id);

        $this->assertNotNull($line, 'The inventory line did not carry its location.');
        $this->assertSame(Warehouse::class, $line->location_type);
    }

    /**
     * A penny out is out.
     *
     * The old check was abs($debit - $credit) > 0.01 on floats, which accepted
     * an entry a cent adrift - and then JournalEntry::post() applied the exact
     * Money check and refused to post it, so it stuck at approved for ever.
     */
    public function test_an_entry_a_penny_out_is_refused(): void
    {
        $this->expectException(UnbalancedEntry::class);

        $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 100.00, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 99.99],
            ],
        ]);
    }

    public function test_a_rollup_account_refuses_a_typed_line(): void
    {
        $rollup = Account::where('is_postable', false)->first() ?? Account::create([
            'code' => '1999',
            'name' => 'Current Assets',
            'account_type_id' => Account::where('is_postable', true)->value('account_type_id'),
            'is_postable' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $rollup->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    public function test_a_closed_period_refuses_a_typed_entry(): void
    {
        $this->expectException(ClosedPeriod::class);

        $this->closeThisMonth();

        $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    /**
     * The gap the period guard could not see.
     *
     * An entry is typed while the month is open and posted after it closes.
     * PostingEngine guards the write; nothing guarded the post, so the one path
     * a person drives by hand was the one that could restate a closed month.
     */
    public function test_a_period_that_closes_before_posting_refuses_the_post(): void
    {
        $entry = $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $this->service->approveEntry($entry);

        $this->closeThisMonth();

        $this->expectException(ClosedPeriod::class);

        $this->service->postEntry($entry->fresh());
    }

    public function test_a_typed_entry_lands_as_a_reviewable_draft(): void
    {
        $entry = $this->service->createManualEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Accrual',
            'lines' => [
                ['account_id' => $this->accountId(AccountRole::Cash), 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountId(AccountRole::SalesRevenue), 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $this->assertSame(JournalEntry::STATUS_DRAFT, $entry->status);
        $this->assertSame(JournalEntry::ORIGIN_MANUAL, $entry->origin);
        $this->assertNull($entry->rule_key, 'A typed entry has no rule behind it.');
    }

    private function closeThisMonth(): void
    {
        app(PeriodCloseService::class)->close(
            \App\Models\FiscalPeriod::findOrCreateFor(Carbon::now()),
        );
    }

    private function accountId(AccountRole $role): int
    {
        return app(\App\Accounting\AccountResolver::class)->idFor($role);
    }
}
