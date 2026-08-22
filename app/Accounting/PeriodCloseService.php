<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closing the books for a period.
 *
 * Revenue and expense accounts measure a period, not a position, so at the end
 * of one they are emptied into retained earnings and start the next at zero.
 * Nothing in this system used to do that, which is why the balance sheet had
 * to derive current-period earnings on every render just to make assets equal
 * liabilities plus equity - a figure that was correct but that no entry backed.
 *
 * With a real closing entry, retained earnings is a posted balance like any
 * other, and the derived line shrinks to what it should always have been: the
 * profit of the period that is still open.
 */
class PeriodCloseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PostingEngine $engine,
        private readonly AccountResolver $accounts,
    ) {
    }

    /**
     * Empty the period's revenue and expense into retained earnings, then
     * close it to further postings.
     */
    public function close(FiscalPeriod $period): FiscalPeriod
    {
        if (! $period->isOpen()) {
            throw new RuntimeException(sprintf('The %s period is already %s.', $period->label(), $period->status));
        }

        return DB::transaction(function () use ($period) {
            $entry = $this->postClosingEntry($period);

            $period->update([
                'status'           => FiscalPeriod::STATUS_CLOSED,
                'closed_at'        => now(),
                'closing_entry_id' => $entry?->id,
            ]);

            return $period->fresh();
        });
    }

    /**
     * Reopen a closed period by reversing its closing entry.
     *
     * The closing entry is not deleted - it is a posted entry like any other,
     * and a posted entry is only ever undone by a further entry that says so.
     */
    public function reopen(FiscalPeriod $period): FiscalPeriod
    {
        if ($period->isLocked()) {
            throw new RuntimeException(sprintf('The %s period is locked and cannot be reopened.', $period->label()));
        }

        if (! $period->isClosed()) {
            throw new RuntimeException(sprintf('The %s period is not closed.', $period->label()));
        }

        return DB::transaction(function () use ($period) {
            // Reopened first, because the reversal is dated inside the period
            // and the engine refuses to post into a period that is not open.
            $period->update(['status' => FiscalPeriod::STATUS_OPEN]);

            if ($closing = $period->closingEntry) {
                $this->engine->reverse($closing, $period->ends_on, 'period reopened');
            }

            $period->update(['closed_at' => null, 'closing_entry_id' => null]);

            return $period->fresh();
        });
    }

    /**
     * Seal a closed period so it can never be reopened.
     */
    public function lock(FiscalPeriod $period): FiscalPeriod
    {
        if (! $period->isClosed()) {
            throw new RuntimeException('Only a closed period can be locked.');
        }

        $period->update(['status' => FiscalPeriod::STATUS_LOCKED]);

        return $period->fresh();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function postClosingEntry(FiscalPeriod $period): ?JournalEntry
    {
        $from = $period->starts_on->toDateString();
        $to = $period->ends_on->toDateString();

        $draft = JournalDraft::for($period, LedgerService::CLOSING_RULE_KEY)
            ->on($period->ends_on)
            ->describedAs('Closing entry - ' . $period->label());

        $net = Money::zero();

        foreach ($this->temporaryAccounts() as $account) {
            $balance = $this->ledger->balance($account, $to, $from);

            if ($balance->isZero()) {
                continue;
            }

            // Post the opposite of what the account stands at, which leaves it
            // at nil however it got there - no assumption about which side a
            // revenue or expense account "should" carry, so a contra account
            // needs no special case.
            $draft->add($account, $balance->negated(), [], 'Closed to retained earnings');

            $net = $net->plus($balance);
        }

        // The net of every temporary account, signed debit-positive: a profit
        // leaves revenue exceeding expense, so $net is negative, and retained
        // earnings is credited by exactly that.
        $draft->add(
            $this->accounts->for(AccountRole::RetainedEarnings),
            $net,
            [],
            'Result for ' . $period->label(),
        );

        return $this->engine->write($draft);
    }

    /**
     * Revenue and expense accounts - the ones that measure a period.
     *
     * @return \Illuminate\Support\Collection<int,Account>
     */
    private function temporaryAccounts()
    {
        return Account::whereHas('accountType', fn ($q) => $q->whereIn('name', ['Revenue', 'Expense']))
            ->orderBy('code')
            ->get();
    }
}
