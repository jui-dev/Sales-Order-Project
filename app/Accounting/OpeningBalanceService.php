<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Opening a set of books that did not start from nothing.
 *
 * There used to be an opening_balance column on every account. It was written
 * as zero by the seeder and read by nothing at all, so a business with stock,
 * cash and debts on the day it started using the system had no way to say so.
 *
 * An opening balance is a journal entry like any other. The contra side goes
 * to Opening Balance Equity, which should return to zero once everything has
 * been entered - if it does not, something has been left out, and that is
 * exactly the signal a suspense account is for.
 */
class OpeningBalanceService
{
    public function __construct(
        private readonly PostingEngine $engine,
        private readonly AccountResolver $accounts,
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * Post the opening position.
     *
     * Amounts are signed debit-positive, matching every other balance in the
     * system: assets positive, liabilities and equity negative.
     *
     * @param array<string,array{amount:Money,party?:Model,location?:Model}> $balances keyed by account code
     */
    public function open(Carbon|string $asOf, array $balances, ?Model $source = null): ?JournalEntry
    {
        $draft = JournalDraft::for($source, 'opening_balance')
            ->on(Carbon::parse($asOf))
            ->describedAs('Opening balances as at ' . Carbon::parse($asOf)->toDateString());

        $net = Money::zero();

        foreach ($balances as $code => $line) {
            $account = Account::where('code', (string) $code)->first();

            if (! $account) {
                throw new RuntimeException(sprintf('No account with code "%s" to open a balance on.', $code));
            }

            $amount = $line['amount'];

            $draft->add(
                $account,
                $amount,
                array_filter([
                    'party'    => $line['party'] ?? null,
                    'location' => $line['location'] ?? null,
                ]),
                'Opening balance',
            );

            $net = $net->plus($amount);
        }

        // Whatever the stated balances do not account for between them is the
        // owner's stake at the moment the books opened.
        $draft->add(
            $this->accounts->for(AccountRole::OpeningBalanceEquity),
            $net->negated(),
            [],
            'Opening balance contra',
        );

        return $this->engine->write($draft);
    }

    /**
     * What is still unexplained in the opening entry.
     *
     * A non-zero figure here means the opening position is incomplete, not
     * that the ledger is wrong.
     */
    public function unexplained(?string $asOf = null): Money
    {
        return $this->ledger->balance(AccountRole::OpeningBalanceEquity, $asOf);
    }
}
