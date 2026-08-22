<?php

namespace App\Services;

use App\Accounting\AccountRole;
use App\Accounting\PostingEngine;
use App\Accounting\Posting\CustomerReturnPostingRule;
use App\Accounting\Posting\RetailerReturnPostingRule;
use App\Accounting\Posting\VendorReturnPostingRule;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\JournalEntry;
use App\Models\StockTransaction;
use InvalidArgumentException;

/**
 * The return-specific face of the ledger.
 *
 * This class used to build the entries itself, in three long methods that
 * named account codes as string literals and duplicated the costing rules. The
 * entries are now built by CustomerReturnPostingRule, VendorReturnPostingRule
 * and RetailerReturnPostingRule alongside every other posting rule, and what
 * is left here is the vocabulary the return services already speak.
 *
 * The approve/post pair survives because the return screens still call it. It
 * no longer changes anything: a return entry is raised by the system from a
 * document that has already been approved, so it is posted when it is created,
 * exactly like an invoice or a supplier bill.
 */
class ReturnJournalHandler
{
    public function __construct(
        private readonly PostingEngine $ledger,
        private readonly CustomerReturnPostingRule $customerReturn,
        private readonly VendorReturnPostingRule $vendorReturn,
        private readonly RetailerReturnPostingRule $retailerReturn,
    ) {
    }

    // ------------------------------------------------------------------
    // Customer returns
    // ------------------------------------------------------------------

    public function createCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        if (! $creditNote->invoice) {
            throw new \Exception('No invoice found for this credit note');
        }

        $entry = $this->ledger->runRule($this->customerReturn, $creditNote);

        if (! $entry) {
            throw new \Exception('The credit note carries no value to post.');
        }

        $this->link($creditNote, $entry, 'linked_credit_note_id', $creditNote->invoice->salesJournal?->id);

        return $entry;
    }

    public function approveCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        return $this->existingEntry($creditNote);
    }

    public function postCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        return $this->existingEntry($creditNote);
    }

    // ------------------------------------------------------------------
    // Vendor returns
    // ------------------------------------------------------------------

    public function createVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        if (! $debitNote->supplierBill) {
            throw new \Exception('No supplier bill found for this debit note');
        }

        $entry = $this->ledger->runRule($this->vendorReturn, $debitNote);

        if (! $entry) {
            throw new \Exception('The debit note carries no value to post.');
        }

        $this->link($debitNote, $entry, 'linked_debit_note_id', $debitNote->supplierBill->purchase_journal_id);

        return $entry;
    }

    public function approveVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        return $this->existingEntry($debitNote);
    }

    public function postVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        return $this->existingEntry($debitNote);
    }

    // ------------------------------------------------------------------
    // Retailer returns
    // ------------------------------------------------------------------

    public function createRetailerReturnJournal(StockTransaction $return): ?JournalEntry
    {
        if (! $return->isRetailerReturn()) {
            throw new InvalidArgumentException('Only retailer returns can post a retailer return journal');
        }

        if (! $return->stockTransfer) {
            throw new \Exception('No stock transfer found for this retailer return.');
        }

        return $this->ledger->runRule($this->retailerReturn, $return);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Record what the entry reverses, and point the note back at it.
     *
     * A return is booked as a new entry that mirrors the original rather than
     * as an edit to it, so the trail reads forwards: here is the sale, here is
     * the return that undid it, and here is the link between them.
     */
    private function link(CreditNote|DebitNote $note, JournalEntry $entry, string $column, ?int $reversesId): void
    {
        $entry->updateQuietly(array_filter([
            'is_reverse'          => true,
            'reverses_journal_id' => $reversesId,
            $column               => $note->getKey(),
        ], fn ($value) => $value !== null));

        $note->update(['journal_entry_id' => $entry->id]);
        $note->setRelation('journalEntry', $entry);
    }

    /**
     * Whether an entry actually looks like the return it claims to be.
     *
     * A balanced entry against the wrong accounts is the failure mode worth
     * catching: it passes every arithmetic check the ledger makes and is still
     * wrong. Cheap enough to assert wherever a return entry is handled.
     */
    public function validateReverseLogic(JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isBalanced()) {
            return false;
        }

        if (! in_array($journalEntry->source_type, [CreditNote::class, DebitNote::class], true)) {
            return false;
        }

        $codes = $journalEntry->lines->pluck('account.code')->filter()->all();

        $required = $journalEntry->source_type === CreditNote::class
            ? [AccountRole::SalesReturns->code(), AccountRole::AccountsReceivable->code()]
            : [AccountRole::AccountsPayable->code(), AccountRole::Inventory->code()];

        return (bool) array_intersect($codes, $required);
    }

    private function existingEntry(CreditNote|DebitNote $note): JournalEntry
    {
        $entry = $note->journalEntry;

        if (! $entry) {
            throw new InvalidArgumentException('No journal entry found for this note');
        }

        return $entry;
    }
}
