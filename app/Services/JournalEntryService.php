<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalEntryService
{
    /**
     * Get filtered journal entries with pagination
     */
    public function getFilteredJournalEntries(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = JournalEntry::with(['lines.account']);

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->whereDate('entry_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('entry_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['journal_type'])) {
            $type = $filters['journal_type'];
            $map = [
                'manual'   => JournalEntry::class,
                'sales'    => \App\Models\Invoice::class,
                'purchase' => \App\Models\SupplierBill::class,
                'stock'    => \App\Models\StockTransfer::class,
                'payment'  => \App\Models\Payment::class,
            ];

            if (array_key_exists($type, $map)) {
                $sourceCls = $map[$type];
                $query->where('source_type', $sourceCls);
            }
        }

        if (!empty($filters['reference'])) {
            $ref = $filters['reference'];
            $query->where(function($sub) use ($ref) {
                $sub->where('description', 'like', "%$ref%")
                    ->orWhere('formatted_id', 'like', "%$ref%");
            });
        }

        if (!empty($filters['account_id'])) {
            $query->whereHas('lines', fn($l) => $l->where('account_id', $filters['account_id']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'date':
                $query->orderBy('entry_date', $direction);
                break;
            case 'status':
                $query->orderBy('status', $direction);
                break;
            case 'amount':
                $query->withSum('lines as total_debit', 'debit')->orderBy('total_debit', $direction);
                break;
            case 'account_type':
                $query->orderBy('id', $direction);
                break;
            default:
                $query->orderBy('id', $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a manual journal entry
     */
    public function createManualEntry(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // Validate that debits equal credits
            $totalDebit = collect($data['lines'])->sum('debit');
            $totalCredit = collect($data['lines'])->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('Total debits must equal total credits.');
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_date' => $data['entry_date'],
                'formatted_id' => $data['reference'] ?? $this->generateFormattedId(),
                'description' => $data['description'],
                'source_type' => JournalEntry::class,
                'source_id' => null,
                'status' => 'draft',
            ]);

            // Create journal entry lines
            foreach ($data['lines'] as $line) {
                $journalEntry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            // Log the creation
            AuditLog::create([
                'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'action' => 'created',
                'model_type' => JournalEntry::class,
                'model_id' => $journalEntry->id,
                'description' => "Created manual journal entry #{$journalEntry->formatted_id}",
                'old_values' => null,
                'new_values' => $journalEntry->toArray(),
            ]);

            return $journalEntry;
        });
    }

    /**
     * Update a journal entry
     */
    public function updateEntry(JournalEntry $journalEntry, array $data): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry, $data) {
            // Check if entry can be edited
            if ($journalEntry->status !== 'draft') {
                throw new \Exception('Only draft entries can be edited.');
            }

            // Validate that debits equal credits
            $totalDebit = collect($data['lines'])->sum('debit');
            $totalCredit = collect($data['lines'])->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('Total debits must equal total credits.');
            }

            // Store old values for audit
            $oldValues = $journalEntry->toArray();

            // Update journal entry
            $journalEntry->update([
                'entry_date' => $data['entry_date'],
                'formatted_id' => $data['reference'] ?? $journalEntry->formatted_id,
                'description' => $data['description'],
            ]);

            // Delete existing lines and create new ones
            $journalEntry->lines()->delete();

            foreach ($data['lines'] as $line) {
                $journalEntry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            // Log the update
            AuditLog::create([
                'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'action' => 'updated',
                'model_type' => JournalEntry::class,
                'model_id' => $journalEntry->id,
                'description' => "Updated journal entry #{$journalEntry->formatted_id}",
                'old_values' => $oldValues,
                'new_values' => $journalEntry->fresh()->toArray(),
            ]);

            return $journalEntry->fresh();
        });
    }

    /**
     * Approve a journal entry
     */
    public function approveEntry(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw new \Exception('Only draft entries can be approved.');
        }

        $journalEntry->update(['status' => 'approved']);

        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'approved',
            'model_type' => JournalEntry::class,
            'model_id' => $journalEntry->id,
            'description' => "Approved journal entry #{$journalEntry->formatted_id}",
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'approved'],
        ]);

        return $journalEntry;
    }

    /**
     * Reject a journal entry
     */
    public function rejectEntry(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw new \Exception('Only draft entries can be rejected.');
        }

        $journalEntry->update(['status' => 'rejected']);

        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'rejected',
            'model_type' => JournalEntry::class,
            'model_id' => $journalEntry->id,
            'description' => "Rejected journal entry #{$journalEntry->formatted_id}",
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'rejected'],
        ]);

        return $journalEntry;
    }

    /**
     * Post a journal entry
     */
    public function postEntry(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status !== 'approved') {
            throw new \Exception('Only approved entries can be posted.');
        }

        $journalEntry->update(['status' => 'posted']);

        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'posted',
            'model_type' => JournalEntry::class,
            'model_id' => $journalEntry->id,
            'description' => "Posted journal entry #{$journalEntry->formatted_id}",
            'old_values' => ['status' => 'approved'],
            'new_values' => ['status' => 'posted'],
        ]);

        return $journalEntry;
    }

    /**
     * Generate a formatted ID for journal entries
     */
    private function generateFormattedId(): string
    {
        $prefix = 'JE';
        $year = date('Y');
        $month = date('m');
        
        $lastEntry = JournalEntry::where('formatted_id', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('formatted_id', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->formatted_id, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%s%s%04d', $prefix, $year, $month, $newNumber);
    }
} 