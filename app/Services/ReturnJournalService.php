<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReturnJournalService
{
    public function __construct(private readonly AccountingService $accountingService)
    {
    }

    /**
     * Create reverse journal entry for customer return (credit note) with draft status
     * This reverses the original sales journal entry
     */
    public function createCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        if ($creditNote->journalEntry && $creditNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Credit note already has a posted journal entry');
        }

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
        $journalEntry = $this->accountingService->post(
            $lines,
            $creditNote->issue_date,
            "Customer Return - Credit Note #{$creditNote->credit_note_number}",
            $creditNote,
            'draft'
        );

        // Update the credit note with journal entry reference
        $creditNote->update(['journal_entry_id' => $journalEntry->id]);

        // Log the creation
        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'customer_return_journal_created',
            'description' => "Customer return journal entry #{$journalEntry->formatted_id} created with draft status for credit note #{$creditNote->credit_note_number}",
            'subject_type' => $creditNote->getMorphClass(),
            'subject_id' => $creditNote->getKey(),
        ]);

        return $journalEntry;
    }

    /**
     * Create reverse journal entry for vendor return (debit note) with draft status
     * This reverses the original purchase journal entry
     */
    public function createVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        if ($debitNote->journalEntry && $debitNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Debit note already has a posted journal entry');
        }

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
        $journalEntry = $this->accountingService->post(
            $lines,
            $debitNote->issue_date,
            "Vendor Return - Debit Note #{$debitNote->debit_note_number}",
            $debitNote,
            'draft'
        );

        // Update the debit note with journal entry reference
        $debitNote->update(['journal_entry_id' => $journalEntry->id]);

        // Log the creation
        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'vendor_return_journal_created',
            'description' => "Vendor return journal entry #{$journalEntry->formatted_id} created with draft status for debit note #{$debitNote->debit_note_number}",
            'subject_type' => $debitNote->getMorphClass(),
            'subject_id' => $debitNote->getKey(),
        ]);

        return $journalEntry;
    }

    /**
     * Post a customer return journal entry (change status from draft to posted)
     */
    public function postCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        $journalEntry = $creditNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this credit note');
        }

        if ($journalEntry->status !== 'draft') {
            throw new \InvalidArgumentException('Journal entry is not in draft status');
        }

        // Post the journal entry
        $journalEntry->post();

        // Log the posting
        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'customer_return_journal_posted',
            'description' => "Customer return journal entry #{$journalEntry->formatted_id} posted for credit note #{$creditNote->credit_note_number}",
            'subject_type' => $creditNote->getMorphClass(),
            'subject_id' => $creditNote->getKey(),
        ]);

        return $journalEntry;
    }

    /**
     * Post a vendor return journal entry (change status from draft to posted)
     */
    public function postVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        $journalEntry = $debitNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this debit note');
        }

        if ($journalEntry->status !== 'draft') {
            throw new \InvalidArgumentException('Journal entry is not in draft status');
        }

        // Post the journal entry
        $journalEntry->post();

        // Log the posting
        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => 'vendor_return_journal_posted',
            'description' => "Vendor return journal entry #{$journalEntry->formatted_id} posted for debit note #{$debitNote->debit_note_number}",
            'subject_type' => $debitNote->getMorphClass(),
            'subject_id' => $debitNote->getKey(),
        ]);

        return $journalEntry;
    }

    /**
     * Get financial impact summary for a return journal
     */
    public function getReturnJournalImpact(JournalEntry $journalEntry): array
    {
        $impact = [
            'income_statement' => [],
            'balance_sheet' => [],
            'cash_flow' => [],
        ];

        foreach ($journalEntry->lines as $line) {
            $accountCode = $line->account->code;
            $amount = $line->debit > 0 ? $line->debit : $line->credit;

            // Income Statement Impact
            if (in_array($accountCode, ['5200', '5100', '5000'])) {
                $impact['income_statement'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $line->debit > 0 ? 'debit' : 'credit',
                ];
            }

            // Balance Sheet Impact
            if (in_array($accountCode, ['1100', '1200', '2100'])) {
                $impact['balance_sheet'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $line->debit > 0 ? 'debit' : 'credit',
                ];
            }

            // Cash Flow Impact (if cash accounts are involved)
            if (in_array($accountCode, ['1000', '1100'])) {
                $impact['cash_flow'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $line->debit > 0 ? 'debit' : 'credit',
                ];
            }
        }

        return $impact;
    }

    /**
     * Validate that a return journal entry is properly balanced
     */
    public function validateReturnJournal(JournalEntry $journalEntry): bool
    {
        if (!$journalEntry->isBalanced()) {
            return false;
        }

        // Additional validation for return journals
        $hasRevenueImpact = $journalEntry->lines()
            ->whereHas('account', function ($q) {
                $q->whereBetween('code', [4000, 4999]); // Revenue accounts
            })
            ->exists();

        $hasExpenseImpact = $journalEntry->lines()
            ->whereHas('account', function ($q) {
                $q->whereBetween('code', [5000, 5999]); // Expense accounts
            })
            ->exists();

        $hasAssetImpact = $journalEntry->lines()
            ->whereHas('account', function ($q) {
                $q->whereBetween('code', [1000, 1999]); // Asset accounts
            })
            ->exists();

        $hasLiabilityImpact = $journalEntry->lines()
            ->whereHas('account', function ($q) {
                $q->whereBetween('code', [2000, 2999]); // Liability accounts
            })
            ->exists();

        // Return journals should have at least revenue/expense impact and asset/liability impact
        return ($hasRevenueImpact || $hasExpenseImpact) && ($hasAssetImpact || $hasLiabilityImpact);
    }

    /**
     * Get return journal statistics
     */
    public function getReturnJournalStatistics(): array
    {
        $customerReturns = JournalEntry::where('description', 'like', '%Customer Return%')
            ->where('status', 'posted')
            ->count();

        $vendorReturns = JournalEntry::where('description', 'like', '%Vendor Return%')
            ->where('status', 'posted')
            ->count();

        $totalCustomerReturnAmount = JournalEntry::where('description', 'like', '%Customer Return%')
            ->where('status', 'posted')
            ->with('lines')
            ->get()
            ->sum(function ($entry) {
                return $entry->lines()
                    ->whereHas('account', function ($q) {
                        $q->where('code', '5200'); // Sales Returns & Allowances
                    })
                    ->sum('debit');
            });

        $totalVendorReturnAmount = JournalEntry::where('description', 'like', '%Vendor Return%')
            ->where('status', 'posted')
            ->with('lines')
            ->get()
            ->sum(function ($entry) {
                return $entry->lines()
                    ->whereHas('account', function ($q) {
                        $q->where('code', '5100'); // Purchase Returns
                    })
                    ->sum('debit');
            });

        return [
            'customer_returns_count' => $customerReturns,
            'vendor_returns_count' => $vendorReturns,
            'total_customer_return_amount' => $totalCustomerReturnAmount,
            'total_vendor_return_amount' => $totalVendorReturnAmount,
            'total_returns_count' => $customerReturns + $vendorReturns,
            'total_return_amount' => $totalCustomerReturnAmount + $totalVendorReturnAmount,
        ];
    }
} 