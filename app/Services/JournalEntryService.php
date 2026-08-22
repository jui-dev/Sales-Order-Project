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
                // Cost of goods sold is raised against the picking list that
                // shipped the goods, and returns against the note that issued
                // them. Without these the entries exist but cannot be filtered to.
                'cogs'           => \App\Models\PickingList::class,
                'customer_return'=> \App\Models\CreditNote::class,
                'vendor_return'  => \App\Models\DebitNote::class,
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

        // System entries are the ledger's own record of a confirmed document
        // and are posted on sight; manual ones are the entries a person typed
        // and the only ones with a review queue. Being able to separate them is
        // what makes that queue findable.
        if (!empty($filters['origin'])) {
            $query->where('origin', $filters['origin']);
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

            // Create journal entry. A manual entry starts as a draft like every
            // generated one and reaches the ledger only once approved and posted.
            //
            // formatted_id and source_id are both NOT NULL, so the row goes in
            // with placeholders and is patched below once the key exists. The
            // source points at the entry itself, matching AccountingService, so
            // the morph columns are never left empty.
            $journalEntry = JournalEntry::create([
                'entry_date'   => $data['entry_date'],
                'description'  => $data['description'] ?? null,
                'source_type'  => JournalEntry::class,
                'source_id'    => 0,
                'status'       => JournalEntry::STATUS_DRAFT,
                // Typed by a person, so it takes the review path and is
                // labelled as such. The column defaults to 'system', which is
                // right for an entry a posting rule raised and wrong here.
                'origin'       => JournalEntry::ORIGIN_MANUAL,
                'formatted_id' => (string) Str::uuid(),
            ]);

            // Fall back to the entry's own code so a manual entry carries the same
            // JE-0000 reference shape as a generated one; a reference the user
            // typed wins over it.
            $journalEntry->forceFill([
                'formatted_id' => ($data['reference'] ?? null) ?: $journalEntry->code,
                'source_id'    => $journalEntry->getKey(),
            ])->saveQuietly();

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
            // audit_logs keys the subject morphically and has no columns for
            // before/after snapshots. Writing model_type/old_values here put a
            // NOT NULL subject_type in breach on every call, so each of these
            // logs threw a QueryException that the controller turned into a
            // flash message - which is what made approving and posting from the
            // journal page fail silently.
            $this->log($journalEntry, 'created', "Created manual journal entry #{$journalEntry->formatted_id}");

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
            if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
                throw new \Exception('Only draft entries can be edited.');
            }

            // Validate that debits equal credits
            $totalDebit = collect($data['lines'])->sum('debit');
            $totalCredit = collect($data['lines'])->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('Total debits must equal total credits.');
            }

            // Update journal entry
            $journalEntry->update([
                'entry_date' => $data['entry_date'],
                'formatted_id' => ($data['reference'] ?? null) ?: $journalEntry->formatted_id,
                'description' => $data['description'] ?? null,
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
            $this->log($journalEntry, 'updated', "Updated journal entry #{$journalEntry->formatted_id}");

            return $journalEntry->fresh();
        });
    }

    /**
     * Approve a journal entry
     */
    public function approveEntry(JournalEntry $journalEntry): JournalEntry
    {
        // Guarded on the model, which also stamps approved_at.
        $journalEntry->approve();

        $this->log($journalEntry, 'approved', "Approved journal entry #{$journalEntry->formatted_id}. Not on the ledger until posted.");

        return $journalEntry;
    }

    /**
     * Reject a journal entry
     */
    public function rejectEntry(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            throw new \Exception('Only draft entries can be rejected.');
        }

        $journalEntry->reject();

        $this->log($journalEntry, 'rejected', "Rejected journal entry #{$journalEntry->formatted_id}");

        return $journalEntry;
    }

    /**
     * Post a journal entry
     */
    public function postEntry(JournalEntry $journalEntry): JournalEntry
    {
        // Guarded on the model: approved only, must balance, stamps posted_at.
        // This is the point at which the entry starts counting in the ledger.
        $journalEntry->post();

        $this->log($journalEntry, 'posted', "Posted journal entry #{$journalEntry->formatted_id} to the ledger.");

        return $journalEntry;
    }

    /**
     * Record an audit trail entry against a journal entry.
     */
    private function log(JournalEntry $journalEntry, string $action, string $description): void
    {
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $journalEntry->getMorphClass(),
            'subject_id'   => $journalEntry->getKey(),
        ]);
    }
} 