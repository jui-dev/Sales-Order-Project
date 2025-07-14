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
    public function post(array $lines, Carbon $date = null, ?string $description = null, ?Model $source = null, string $status = 'approved'): JournalEntry
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
                'formatted_id'=> (string) Str::uuid(),
            ]);

            // Generate formatted_id (e.g., JE-000001) and save once
            if (empty($entry->formatted_id)) {
                $entry->formatted_id = 'JE-' . str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT);
                $entry->saveQuietly();
            }

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

            // Record audit log
            AuditLog::create([
                'user_id'      => auth()->id(),
                'action'       => 'journal_posted',
                'description'  => 'Journal Entry ' . ($entry->formatted_id ?? $entry->id) . ' posted.',
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
            ->whereHas('journalEntry', fn($j) => $j->whereIn('status', ['posted','approved']))
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

        // Only approved journal entries
        $query->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted','approved']));

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
        $entry->approve();

        // Optional: dispatch events, audit log
        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'journal_approved',
            'description'  => 'Journal Entry ' . ($entry->formatted_id ?? $entry->id) . ' approved.',
            'subject_type' => $entry->getMorphClass(),
            'subject_id'   => $entry->getKey(),
        ]);
    }
} 