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
     * Create journal entry for credit note with draft status
     */
    public function createCreditNoteJournalEntry(\App\Models\CreditNote $creditNote): JournalEntry
    {
        $lines = [];
        $totalRefund = $creditNote->total_amount;

        // Sales Returns & Allowances (Contra Revenue) - Debit (reduces revenue)
        $lines[] = [
            'account_code' => '5200', // Sales Returns & Allowances
            'debit' => $totalRefund,
            'credit' => 0,
            'description' => "Customer Return - Credit Note #{$creditNote->credit_note_number}",
        ];

        // Accounts Receivable - Credit (reduces receivable)
        $lines[] = [
            'account_code' => '1100', // Accounts Receivable
            'debit' => 0,
            'credit' => $totalRefund,
            'description' => "Customer Return - Credit Note #{$creditNote->credit_note_number}",
        ];

        // Calculate total cost of returned items for inventory adjustment
        $totalCost = $creditNote->items->sum(function ($item) {
            return $item->quantity * ($item->product->purchase_price ?? 0);
        });

        if ($totalCost > 0) {
            // Inventory - Debit (put inventory back)
            $lines[] = [
                'account_code' => '1200', // Inventory
                'debit' => $totalCost,
                'credit' => 0,
                'description' => "Customer Return - Inventory Return #{$creditNote->credit_note_number}",
            ];

            // Cost of Goods Sold - Credit (reverse COGS)
            $lines[] = [
                'account_code' => '5000', // Cost of Goods Sold
                'debit' => 0,
                'credit' => $totalCost,
                'description' => "Customer Return - Reverse COGS #{$creditNote->credit_note_number}",
            ];
        }

        // Create journal entry with draft status (no immediate financial impact)
        return $this->post(
            $lines,
            $creditNote->issue_date,
            "Customer Return - Credit Note #{$creditNote->credit_note_number}",
            $creditNote,
            'draft'
        );
    }

    /**
     * Create journal entry for debit note with draft status
     */
    public function createDebitNoteJournalEntry(\App\Models\DebitNote $debitNote): JournalEntry
    {
        $lines = [];
        $totalAmount = $debitNote->total_amount;

        // Purchase Returns (Contra Expense) - Debit (reduces expense)
        $lines[] = [
            'account_code' => '5100', // Purchase Returns
            'debit' => $totalAmount,
            'credit' => 0,
            'description' => "Vendor Return - Debit Note #{$debitNote->debit_note_number}",
        ];

        // Accounts Payable - Credit (reduces payable)
        $lines[] = [
            'account_code' => '2100', // Accounts Payable
            'debit' => 0,
            'credit' => $totalAmount,
            'description' => "Vendor Return - Debit Note #{$debitNote->debit_note_number}",
        ];

        // Calculate total cost of returned items for inventory adjustment
        $totalCost = $debitNote->items->sum(function ($item) {
            return $item->quantity * ($item->product->purchase_price ?? 0);
        });

        if ($totalCost > 0) {
            // Inventory - Credit (reduce inventory)
            $lines[] = [
                'account_code' => '1200', // Inventory
                'debit' => 0,
                'credit' => $totalCost,
                'description' => "Vendor Return - Inventory Reduction #{$debitNote->debit_note_number}",
            ];

            // Cost of Goods Sold - Debit (reverse COGS)
            $lines[] = [
                'account_code' => '5000', // Cost of Goods Sold
                'debit' => $totalCost,
                'credit' => 0,
                'description' => "Vendor Return - Reverse COGS #{$debitNote->debit_note_number}",
            ];
        }

        // Create journal entry with draft status (no immediate financial impact)
        return $this->post(
            $lines,
            $debitNote->issue_date,
            "Vendor Return - Debit Note #{$debitNote->debit_note_number}",
            $debitNote,
            'draft'
        );
    }

    /**
     * Post a journal entry (change status from draft to posted)
     */
    public function postJournalEntry(JournalEntry $journalEntry): void
    {
        if ($journalEntry->status !== 'draft') {
            throw new InvalidArgumentException('Only draft journal entries can be posted');
        }

        $journalEntry->update([
            'status' => 'posted',
            'posted_at' => now(),
        ]);

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