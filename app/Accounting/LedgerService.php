<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every read of the ledger.
 *
 * Posting is a visibility gate rather than a computation: nothing in this
 * system stores a balance, so every figure is derived here by summing the
 * lines of posted entries. That is worth keeping - it means a balance can
 * never drift from the entries behind it - but it has to be done in SQL. The
 * reports used to pull every line of every account into PHP and sum them with
 * a closure, once per account, on every render.
 *
 * Balances are signed debit-minus-credit throughout. Assets and expenses come
 * out positive, liabilities, equity and revenue negative, and a contra account
 * the opposite of its type. Presentation flips the sign; nothing here does.
 */
class LedgerService
{
    /** The rule key every period-closing entry is written under. */
    public const CLOSING_RULE_KEY = 'period.close';

    private bool $includeClosing = true;

    public function __construct(
        private readonly AccountResolver $accounts,
    ) {
    }

    /**
     * A reader that ignores period-closing entries.
     *
     * Closing entries zero revenue and expense into retained earnings and are
     * dated on the last day of the period they close. An income statement for
     * that period must not see them, or every closed period reports nil profit.
     * A balance sheet must see them, because that is where retained earnings
     * comes from.
     */
    public function excludingClosingEntries(): self
    {
        $clone = clone $this;
        $clone->includeClosing = false;

        return $clone;
    }

    // ------------------------------------------------------------------
    // Single balances
    // ------------------------------------------------------------------

    /**
     * What an account stands at, optionally as at a date and within a period.
     */
    public function balance(
        AccountRole|Account|int $account,
        ?string $asOf = null,
        ?string $from = null,
    ): Money {
        $row = $this->lineQuery($asOf, $from)
            ->where('journal_entry_lines.account_id', $this->accountId($account))
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS signed')
            ->value('signed');

        return Money::of((string) ($row ?? 0));
    }

    /**
     * The same, for several accounts at once.
     *
     * @param iterable<AccountRole|Account|int> $accounts
     */
    public function balanceOf(iterable $accounts, ?string $asOf = null, ?string $from = null): Money
    {
        $ids = [];

        foreach ($accounts as $account) {
            $ids[] = $this->accountId($account);
        }

        if ($ids === []) {
            return Money::zero();
        }

        $row = $this->lineQuery($asOf, $from)
            ->whereIn('journal_entry_lines.account_id', $ids)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS signed')
            ->value('signed');

        return Money::of((string) ($row ?? 0));
    }

    // ------------------------------------------------------------------
    // Grouped balances
    // ------------------------------------------------------------------

    /**
     * Signed balance per account, keyed by account id.
     *
     * @return Collection<int,Money>
     */
    public function balancesByAccount(?string $asOf = null, ?string $from = null): Collection
    {
        return $this->lineQuery($asOf, $from)
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) AS debit, SUM(journal_entry_lines.credit) AS credit')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->account_id => Money::of((string) $row->debit)->minus(Money::of((string) $row->credit)),
            ]);
    }

    /**
     * What each party owes, or is owed, on a control account.
     *
     * This is the subsidiary ledger, read straight off the general ledger
     * rather than rebuilt from the invoices and hoped to agree. It only works
     * because every line against a control account is required to carry its
     * party.
     *
     * @return Collection<string,array{party_type:string,party_id:int,balance:Money}>
     */
    public function partyBalances(AccountRole $role, ?string $asOf = null): Collection
    {
        return $this->lineQuery($asOf)
            ->where('journal_entry_lines.account_id', $this->accountId($role))
            ->whereNotNull('journal_entry_lines.party_id')
            ->groupBy('journal_entry_lines.party_type', 'journal_entry_lines.party_id')
            ->selectRaw('journal_entry_lines.party_type, journal_entry_lines.party_id, SUM(journal_entry_lines.debit) AS debit, SUM(journal_entry_lines.credit) AS credit')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->party_type . ':' . $row->party_id => [
                    'party_type' => $row->party_type,
                    'party_id'   => (int) $row->party_id,
                    'balance'    => Money::of((string) $row->debit)->minus(Money::of((string) $row->credit)),
                ],
            ]);
    }

    /**
     * Inventory value per stock location.
     *
     * A grouping of the one inventory account, so the parts necessarily add up
     * to the whole. Per-location value used to live in sub-accounts that only
     * transfers ever touched, so they added up to nothing in particular.
     *
     * @return Collection<string,array{location_type:string,location_id:int,balance:Money}>
     */
    public function locationBalances(AccountRole $role, ?string $asOf = null): Collection
    {
        return $this->lineQuery($asOf)
            ->where('journal_entry_lines.account_id', $this->accountId($role))
            ->whereNotNull('journal_entry_lines.location_id')
            ->groupBy('journal_entry_lines.location_type', 'journal_entry_lines.location_id')
            ->selectRaw('journal_entry_lines.location_type, journal_entry_lines.location_id, SUM(journal_entry_lines.debit) AS debit, SUM(journal_entry_lines.credit) AS credit')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->location_type . ':' . $row->location_id => [
                    'location_type' => $row->location_type,
                    'location_id'   => (int) $row->location_id,
                    'balance'       => Money::of((string) $row->debit)->minus(Money::of((string) $row->credit)),
                ],
            ]);
    }

    /**
     * Signed movement per day across a set of accounts.
     *
     * A period total says what was earned; this says when. Grouping on the
     * entry date rather than on any document date is what keeps a daily report
     * summing to the statement that covers the same range.
     *
     * @param  iterable<AccountRole|Account|int> $accounts
     * @return Collection<string,Money>  keyed by Y-m-d
     */
    public function dailyMovement(iterable $accounts, ?string $from = null, ?string $to = null): Collection
    {
        $ids = $this->accountIds($accounts);

        if ($ids === []) {
            return collect();
        }

        return $this->lineQuery($to, $from)
            ->whereIn('journal_entry_lines.account_id', $ids)
            ->groupBy('journal_entries.entry_date')
            ->selectRaw('journal_entries.entry_date, COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS signed')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->entry_date)->toDateString() => Money::of((string) $row->signed),
            ]);
    }

    /**
     * Signed movement per product across a set of accounts.
     *
     * Revenue and cost of sales are single accounts carrying a product
     * dimension, so what one product earned is a GROUP BY over its lines and
     * the parts necessarily add up to the whole - the same reason inventory is
     * not split into per-warehouse accounts.
     *
     * Lines with no product are excluded rather than bucketed: an invoice with
     * no line breakdown posts unattributed revenue, and folding that into any
     * one product would be worse than leaving it out of a per-product view.
     * The totals are read separately and still include it.
     *
     * @param  iterable<AccountRole|Account|int> $accounts
     * @param  array<int,int>|null $productIds  restrict to these, for a paginated page
     * @return Collection<int,Money>  keyed by product id
     */
    public function movementByProduct(
        iterable $accounts,
        ?string $from = null,
        ?string $to = null,
        ?array $productIds = null,
    ): Collection {
        $ids = $this->accountIds($accounts);

        if ($ids === [] || $productIds === []) {
            return collect();
        }

        return $this->lineQuery($to, $from)
            ->whereIn('journal_entry_lines.account_id', $ids)
            ->whereNotNull('journal_entry_lines.product_id')
            ->when($productIds !== null, fn ($q) => $q->whereIn('journal_entry_lines.product_id', $productIds))
            ->groupBy('journal_entry_lines.product_id')
            ->selectRaw('journal_entry_lines.product_id, COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS signed')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->product_id => Money::of((string) $row->signed),
            ]);
    }

    /**
     * Signed movement per day, product and location across a set of accounts.
     *
     * The detail behind the daily profit report: one row per thing sold, per
     * place it sold from, per day it landed on the books.
     *
     * @param  iterable<AccountRole|Account|int> $accounts
     * @return Collection<int,array{date:string,product_id:int,location_type:?string,location_id:?int,amount:Money}>
     */
    public function movementByProductAndLocation(iterable $accounts, ?string $from = null, ?string $to = null): Collection
    {
        $ids = $this->accountIds($accounts);

        if ($ids === []) {
            return collect();
        }

        return $this->lineQuery($to, $from)
            ->whereIn('journal_entry_lines.account_id', $ids)
            ->whereNotNull('journal_entry_lines.product_id')
            ->groupBy(
                'journal_entries.entry_date',
                'journal_entry_lines.product_id',
                'journal_entry_lines.location_type',
                'journal_entry_lines.location_id',
            )
            ->selectRaw(
                'journal_entries.entry_date, journal_entry_lines.product_id, '
                . 'journal_entry_lines.location_type, journal_entry_lines.location_id, '
                . 'COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS signed'
            )
            ->get()
            ->map(fn ($row) => [
                'date'          => Carbon::parse($row->entry_date)->toDateString(),
                'product_id'    => (int) $row->product_id,
                'location_type' => $row->location_type,
                'location_id'   => $row->location_id === null ? null : (int) $row->location_id,
                'amount'        => Money::of((string) $row->signed),
            ]);
    }

    // ------------------------------------------------------------------
    // Trial balance
    // ------------------------------------------------------------------

    /**
     * Total debits and total credits, which must be equal.
     *
     * @return array{debit:Money,credit:Money,difference:Money,balanced:bool}
     */
    public function trialBalanceTotals(?string $asOf = null): array
    {
        $row = $this->lineQuery($asOf)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();

        $debit = Money::of((string) ($row->debit ?? 0));
        $credit = Money::of((string) ($row->credit ?? 0));

        return [
            'debit'      => $debit,
            'credit'     => $credit,
            'difference' => $debit->minus($credit),
            'balanced'   => $debit->equals($credit),
        ];
    }

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------

    /**
     * The posted lines of one account, newest entry last.
     */
    public function ledgerFor(AccountRole|Account|int $account, ?string $from = null, ?string $to = null)
    {
        return JournalEntryLine::query()
            ->where('account_id', $this->accountId($account))
            ->whereHas('journalEntry', function (Builder $query) use ($from, $to) {
                $query->where('status', JournalEntry::STATUS_POSTED);

                if ($from) {
                    $query->whereDate('entry_date', '>=', $from);
                }

                if ($to) {
                    $query->whereDate('entry_date', '<=', $to);
                }
            })
            ->with(['journalEntry', 'account', 'party', 'location', 'product'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.journal_entry_id')
            ->select('journal_entry_lines.*')
            ->get();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Posted lines only, within an optional date window.
     *
     * Posting is the single gate onto the books. Every reader goes through
     * here so that no two of them can disagree about what counts - which is
     * how the balance sheet once drew its line items from one set of entries
     * and its totals from another.
     */
    private function lineQuery(?string $asOf = null, ?string $from = null)
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED);

        if (! $this->includeClosing) {
            $query->where(function ($q) {
                $q->whereNull('journal_entries.rule_key')
                  ->orWhere('journal_entries.rule_key', '!=', self::CLOSING_RULE_KEY);
            });
        }

        if ($from) {
            $query->whereDate('journal_entries.entry_date', '>=', $from);
        }

        if ($asOf) {
            $query->whereDate('journal_entries.entry_date', '<=', $asOf);
        }

        return $query;
    }

    /**
     * @param  iterable<AccountRole|Account|int> $accounts
     * @return array<int,int>
     */
    private function accountIds(iterable $accounts): array
    {
        $ids = [];

        foreach ($accounts as $account) {
            $ids[] = $this->accountId($account);
        }

        return $ids;
    }

    private function accountId(AccountRole|Account|int $account): int
    {
        return match (true) {
            $account instanceof AccountRole => $this->accounts->idFor($account),
            $account instanceof Account     => $account->id,
            default                         => $account,
        };
    }
}
