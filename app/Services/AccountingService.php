<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Str;

class AccountingService
{
    /**
     * Post a balanced journal entry.
     *
     * @param array<int,array{account_code?:string,account_id?:int,debit:float,credit:float,description?:string}> $lines
     */
    public function post(array $lines, Carbon $date = null, ?string $description = null, ?Model $source = null, string $status = 'draft'): JournalEntry
    {
        $date = $date ?: Carbon::now();

        // Validate totals
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit += $line['debit'] ?? 0;
            $totalCredit += $line['credit'] ?? 0;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException('Journal entry is not balanced.');
        }

        if ($totalDebit <= 0) {
            throw new InvalidArgumentException('Journal entry totals must be greater than zero.');
        }

        return DB::transaction(function () use ($lines, $date, $description, $source, $status) {
            $entry = JournalEntry::create([
                'entry_date'  => $date,
                'description' => $description,
                'status'      => $status,
                // If no external model supplied, we will temporarily set blank values and later
                // patch them to a self-reference so the morph columns are never left empty.
                'source_type' => $source ? $source->getMorphClass() : '',
                'source_id'   => $source ? $source->getKey() : 0,
                // The real reference needs the primary key, which only exists once
                // the row is in. A unique placeholder holds the slot until then.
                'formatted_id'=> (string) Str::uuid(),
            ]);

            // Store the same reference the entry displays. The column used to keep
            // the placeholder UUID forever - the branch that replaced it tested
            // `empty()` on a value that had just been filled - which left search
            // and the uniqueness rule matching against a string no user ever saw.
            $entry->forceFill(['formatted_id' => $entry->code])->saveQuietly();

            foreach ($lines as $line) {
                $account = null;
                if (isset($line['account_id'])) {
                    $account = Account::find($line['account_id']);
                } elseif (isset($line['account_code'])) {
                    $account = Account::where('code', $line['account_code'])->first();
                }

                if (!$account) {
                    throw new InvalidArgumentException('Account not found for line.');
                }

                $entry->lines()->create([
                    'account_id' => $account->id,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            // ------------------------------------------------------------------
            // Guarantee valid morph columns to avoid queries with empty column names
            // ------------------------------------------------------------------
            if (! $source) {
                // Update quietly to point to itself when the entry has no external source
                $entry->updateQuietly([
                    'source_type' => $entry->getMorphClass(),
                    'source_id'   => $entry->getKey(),
                ]);
            }

            // Record audit log depending on initial status
            $logAction = ($status === JournalEntry::STATUS_POSTED || $status === JournalEntry::STATUS_APPROVED)
                ? 'journal_posted'
                : 'journal_created';

            AuditLog::create([
                'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'action'       => $logAction,
                'description'  => 'Journal Entry ' . ($entry->formatted_id ?? $entry->id) . ' created with status '.ucfirst($status).'.',
                'subject_type' => $entry->getMorphClass(),
                'subject_id'   => $entry->getKey(),
            ]);

            return $entry;
        });
    }

    /**
     * Retrieve ledger lines for an account within a date range.
     */
    public function ledger(Account|int $account, ?Carbon $from = null, ?Carbon $to = null)
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        return $account->journalEntryLines()
            ->whereHas('journalEntry', fn($j) => $j->where('status', JournalEntry::STATUS_POSTED))
            ->when($from, fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('entry_date', '>=', $from)))
            ->when($to, fn($q) => $q->whereHas('journalEntry', fn($j) => $j->whereDate('entry_date', '<=', $to)))
            ->with('journalEntry')
            ->orderBy('journal_entry_id')
            ->get();
    }

    /**
     * Generate a trial balance up to a given date.
     * Returns collection keyed by account code with debit & credit totals.
     */
    public function trialBalance(?Carbon $to = null)
    {
        $query = JournalEntryLine::query()
            ->select('account_id', DB::raw('SUM(debit) as debit'), DB::raw('SUM(credit) as credit'))
            ->groupBy('account_id')
            ->with('account');

        if ($to) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $to));
        }

        // Posting is the only thing that puts an entry on the books; an approved
        // entry has cleared review but has not been booked yet.
        $query->whereHas('journalEntry', fn($q) => $q->where('status', JournalEntry::STATUS_POSTED));

        return $query->get()->mapWithKeys(function ($row) {
            return [$row->account->code => [
                'account' => $row->account,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ]];
        });
    }

    public function approveEntry(JournalEntry $entry): void
    {
        // Guarded on the model: draft is the only status that can be approved.
        $entry->approve();

        // Optional: dispatch events, audit log
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => 'journal_approved',
            'description'  => 'Journal Entry ' . ($entry->formatted_id ?? $entry->id) . ' approved.',
            'subject_type' => $entry->getMorphClass(),
            'subject_id'   => $entry->getKey(),
        ]);
    }

    /**
     * Create a draft journal entry (alias for post with draft status).
     *
     * @param array<int,array{account_code?:string,account_id?:int,debit:float,credit:float,description?:string}> $lines
     */
    public function createDraft(array $lines, Carbon $date = null, ?string $description = null, ?Model $source = null): JournalEntry
    {
        return $this->post($lines, $date, $description, $source, 'draft');
    }

    /**
     * Create a return journal entry with draft status.
     *
     * @param array<int,array{account_code?:string,account_id?:int,debit:float,credit:float,description?:string}> $lines
     */
    public function createReturnEntry(array $lines, Carbon $date = null, ?string $description = null, ?Model $source = null): JournalEntry
    {
        return $this->post($lines, $date, $description, $source, 'draft');
    }

    /**
     * Post a journal entry (change status from approved to posted)
     *
     * Credit- and debit-note entries are built by ReturnJournalHandler, which is
     * the only place that logic lives. They used to jump straight from draft to
     * posted here while every other entry had to be approved first, which is why
     * a return could reach the trial balance ahead of the sale it reverses.
     */
    public function postJournalEntry(JournalEntry $journalEntry): void
    {
        // Throws unless the entry is approved and balanced.
        $journalEntry->post();

        // Log the posting
        AuditLog::create([
            'user_id' => auth()->id() ?? 1,
            'action' => 'journal_posted',
            'description' => "Journal entry #{$journalEntry->formatted_id} posted",
            'subject_type' => $journalEntry->getMorphClass(),
            'subject_id' => $journalEntry->getKey(),
        ]);
    }
} 