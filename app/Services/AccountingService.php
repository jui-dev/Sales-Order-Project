<?php

namespace App\Services;

use App\Accounting\AccountResolver;
use App\Accounting\JournalDraft;
use App\Accounting\LedgerService;
use App\Accounting\PostingEngine;
use App\Accounting\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @deprecated Use App\Accounting\PostingEngine to write and
 *             App\Accounting\LedgerService to read.
 *
 * What is left of the old front door, and now a test fixture rather than a code
 * path. Nothing in app/ calls it: every system entry is built by a posting rule
 * and written by the engine, and the manual-entry path went the same way when
 * JournalEntryService moved onto ManualEntryDraft and PostingEngine. What keeps
 * this class alive is that five test files find "build me an entry from account
 * codes" a convenient thing to say, and that is a reasonable thing for a test
 * to want.
 *
 * It delegates and adds nothing, so an entry raised through it still passes the
 * period guard, the exact Money balance check and the engine's own writing. It
 * is not a way round any of them. Do not call it from application code - reach
 * for PostingEngine directly, which is the only thing that decides what reaches
 * the ledger.
 *
 * Note that its post() never posted - it created a draft, whatever the name
 * said. That is preserved here, because a manual entry genuinely does belong
 * in draft until somebody has reviewed it.
 */
class AccountingService
{
    public function __construct(
        private readonly PostingEngine $engine,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {
    }

    /**
     * Create a manual journal entry from raw account codes.
     *
     * @param array<int,array{account_code?:string,account_id?:int,debit?:float,credit?:float,description?:string}> $lines
     */
    public function post(
        array $lines,
        ?Carbon $date = null,
        ?string $description = null,
        ?Model $source = null,
        string $status = JournalEntry::STATUS_DRAFT,
    ): JournalEntry {
        $draft = JournalDraft::for($source)
            ->on($date ?? Carbon::now())
            ->describedAs($description);

        foreach ($lines as $line) {
            $account = $this->resolve($line);

            $draft->add(
                $account,
                Money::of((string) ($line['debit'] ?? 0))->minus(Money::of((string) ($line['credit'] ?? 0))),
                [],
                $line['description'] ?? null,
            );
        }

        if ($draft->isEmpty()) {
            throw new InvalidArgumentException('Journal entry totals must be greater than zero.');
        }

        if (! $draft->isBalanced()) {
            throw new InvalidArgumentException('Journal entry is not balanced.');
        }

        $entry = $this->engine->write(
            $draft,
            $status === JournalEntry::STATUS_POSTED ? JournalEntry::ORIGIN_SYSTEM : JournalEntry::ORIGIN_MANUAL,
        );

        if (! $entry) {
            throw new InvalidArgumentException('Journal entry totals must be greater than zero.');
        }

        return $entry;
    }

    /** @param array<string,mixed> $line */
    private function resolve(array $line): Account
    {
        $account = isset($line['account_id'])
            ? Account::find($line['account_id'])
            : Account::where('code', $line['account_code'] ?? null)->first();

        if (! $account) {
            throw new InvalidArgumentException('Account not found for line.');
        }

        return $account;
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     */
    public function createDraft(array $lines, ?Carbon $date = null, ?string $description = null, ?Model $source = null): JournalEntry
    {
        return $this->post($lines, $date, $description, $source);
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     */
    public function createReturnEntry(array $lines, ?Carbon $date = null, ?string $description = null, ?Model $source = null): JournalEntry
    {
        return $this->post($lines, $date, $description, $source);
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    public function ledger(Account|int $account, ?Carbon $from = null, ?Carbon $to = null)
    {
        return $this->ledger->ledgerFor(
            $account instanceof Account ? $account : Account::findOrFail($account),
            $from?->toDateString(),
            $to?->toDateString(),
        );
    }

    /**
     * Trial balance keyed by account code.
     */
    public function trialBalance(?Carbon $to = null)
    {
        $balances = $this->ledger->balancesByAccount($to?->toDateString());
        $accounts = Account::whereIn('id', $balances->keys()->all())->get()->keyBy('id');

        return $balances->mapWithKeys(function (Money $signed, int $accountId) use ($accounts) {
            $account = $accounts[$accountId] ?? null;

            if (! $account) {
                return [];
            }

            return [$account->code => [
                'account' => $account,
                'debit'   => $signed->isPositive() ? $signed->toFloat() : 0.0,
                'credit'  => $signed->isNegative() ? $signed->absolute()->toFloat() : 0.0,
            ]];
        });
    }

    // ------------------------------------------------------------------
    // Manual review path
    // ------------------------------------------------------------------

    public function approveEntry(JournalEntry $entry): void
    {
        $entry->approve();

        $this->log($entry, 'journal_approved', 'approved');
    }

    public function postJournalEntry(JournalEntry $journalEntry): void
    {
        $journalEntry->post();

        $this->log($journalEntry, 'journal_posted', 'posted');
    }

    private function log(JournalEntry $entry, string $action, string $verb): void
    {
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1,
            'action'       => $action,
            'description'  => 'Journal Entry ' . $entry->formatted_id . ' ' . $verb . '.',
            'subject_type' => $entry->getMorphClass(),
            'subject_id'   => $entry->getKey(),
        ]);
    }
}
