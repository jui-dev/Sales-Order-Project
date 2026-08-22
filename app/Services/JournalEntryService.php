<?php

namespace App\Services;

use App\Accounting\ManualEntryDraft;
use App\Accounting\PostingEngine;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The journal entry screens, and nothing that writes to the ledger.
 *
 * This class used to call JournalEntry::create() and lines()->create() itself,
 * which made it the only writer outside app/Accounting - and the only one that
 * skipped the period guard, the exact balance check, the requirement that a
 * control account names its party, and the refusal to post to a rollup
 * account. Entries are built as a JournalDraft and written by PostingEngine
 * now, exactly like every entry a posting rule raises.
 */
class JournalEntryService
{
    public function __construct(
        private readonly PostingEngine $ledger,
    ) {
    }

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
     * Create a manual journal entry.
     *
     * It lands as a draft and takes the approve-then-post path: review belongs
     * on entries a person typed, which is exactly what origin distinguishes.
     */
    public function createManualEntry(array $data): JournalEntry
    {
        $draft = ManualEntryDraft::from($data);

        $entry = $this->ledger->write($draft, JournalEntry::ORIGIN_MANUAL);

        if (! $entry) {
            throw new \RuntimeException('An entry needs at least one line with a value on it.');
        }

        // A reference the user typed wins over the JE-0000 the engine derives.
        // JournalEntry overrides the HasFormattedId accessor to prefer the
        // stored value, so unlike the other documents this column is read.
        if (! empty($data['reference'])) {
            $entry->forceFill(['formatted_id' => $data['reference']])->saveQuietly();
        }

        $this->log($entry, 'created', "Created manual journal entry #{$entry->formatted_id}");

        return $entry->fresh();
    }

    /**
     * Update a journal entry.
     *
     * Only a draft can be edited; PostingEngine::rewrite() enforces that as
     * well as the balance, the dimensions and the period, so an edit cannot
     * put an entry into a state creating it would have refused.
     */
    public function updateEntry(JournalEntry $journalEntry, array $data): JournalEntry
    {
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            throw new \Exception('Only draft entries can be edited.');
        }

        $entry = $this->ledger->rewrite($journalEntry, ManualEntryDraft::from($data));

        if (! empty($data['reference'])) {
            $entry->forceFill(['formatted_id' => $data['reference']])->saveQuietly();
            $entry = $entry->fresh();
        }

        $this->log($entry, 'updated', "Updated journal entry #{$entry->formatted_id}");

        return $entry;
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