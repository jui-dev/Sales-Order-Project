<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReturnJournalHandler
{
    public function __construct(private readonly AccountingService $accountingService)
    {
    }

    /**
     * Create reverse journal entry for customer return with built-in reverse logic
     * This automatically reverses the original sales journal entry
     */
    public function createCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        if ($creditNote->journalEntry && $creditNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Credit note already has a posted journal entry');
        }

        return DB::transaction(function () use ($creditNote) {
            // Get the original invoice to understand the sales structure
            $invoice = $creditNote->invoice;
            if (!$invoice) {
                throw new \Exception('No invoice found for this credit note');
            }

            // Find the original sales journal entry
            $originalSalesJournal = $invoice->salesJournal;
            if (!$originalSalesJournal) {
                // Log a warning and abort the journal creation process
                \Log::warning("Original sales journal not found for invoice #{$invoice->invoice_number}. Aborting journal creation for credit note #{$creditNote->credit_note_number}.");
                throw new \Exception('Original sales journal not found. Cannot create reverse journal entry.');
            }

            $lines = [];
            $totalRefund = $creditNote->total_amount;

            // ========================================
            // REVERSE THE ORIGINAL SALES JOURNAL ENTRY
            // ========================================
            
            // 1. Reverse Sales Revenue → Sales Returns & Allowances (Contra Revenue)
            // Original: Credit Sales Revenue (4000)
            // Reverse:  Debit Sales Returns & Allowances (5200) - reduces revenue
            $lines[] = [
                'account_code' => '5200', // Sales Returns & Allowances (Contra Revenue)
                'debit' => $totalRefund,
                'credit' => 0,
                'description' => "Customer Return - Reverse Sales Revenue #{$creditNote->credit_note_number}",
            ];

            // 2. Reverse Accounts Receivable
            // Original: Debit Accounts Receivable (1100)
            // Reverse:  Credit Accounts Receivable (1100) - reduces receivable
            $lines[] = [
                'account_code' => '1100', // Accounts Receivable
                'debit' => 0,
                'credit' => $totalRefund,
                'description' => "Customer Return - Reverse Accounts Receivable #{$creditNote->credit_note_number}",
            ];

            // ========================================
            // INVENTORY AND COGS ADJUSTMENT
            // ========================================
            
            // Calculate total cost of returned items for inventory adjustment
            $totalCost = $creditNote->items->sum(function ($item) {
                return $item->quantity * ($item->product->purchase_price ?? 0);
            });

            if ($totalCost > 0) {
                // 3. Return inventory (reverse the inventory entry)
                // Original: Credit Inventory (1200) - when sold
                // Reverse: Debit Inventory (1200) - put inventory back
                $lines[] = [
                    'account_code' => '1200', // Inventory
                    'debit' => $totalCost,
                    'credit' => 0,
                    'description' => "Customer Return - Inventory Return #{$creditNote->credit_note_number}",
                ];

                // 4. Reverse Cost of Goods Sold
                // Original: Debit Cost of Goods Sold (5000)
                // Reverse: Credit Cost of Goods Sold (5000) - reverse COGS
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
                "Customer Return - Reverse Sales Journal #{$creditNote->credit_note_number}",
                $creditNote,
                'draft'
            );

            // Update the journal entry with reverse journal metadata
            $journalEntry->update([
                'is_reverse' => true,
                'reverses_journal_id' => $originalSalesJournal->id,
                'linked_credit_note_id' => $creditNote->id,
            ]);

            // Update the credit note with journal entry reference
            $creditNote->update(['journal_entry_id' => $journalEntry->id]);

            // The guard above already read `journalEntry`, caching it as null.
            // Refresh it so approving and posting can follow on the same
            // instance without re-fetching the credit note.
            $creditNote->setRelation('journalEntry', $journalEntry);

            // Log the creation with reverse logic details
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'customer_return_journal_created',
                'description' => "Customer return journal entry #{$journalEntry->formatted_id} created with built-in reverse logic for credit note #{$creditNote->credit_note_number}. Original sales journal: #{$originalSalesJournal->formatted_id}",
                'subject_type' => $creditNote->getMorphClass(),
                'subject_id' => $creditNote->getKey(),
                'metadata' => [
                    'reverse_logic_applied' => true,
                    'original_sales_invoice' => $invoice->invoice_number,
                    'original_sales_journal' => $originalSalesJournal->formatted_id,
                    'reverses_journal_id' => $originalSalesJournal->id,
                    'linked_credit_note_id' => $creditNote->id,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Create reverse journal entry for vendor return with built-in reverse logic
     * This automatically reverses the original purchase journal entry
     */
    public function createVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        if ($debitNote->journalEntry && $debitNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Debit note already has a posted journal entry');
        }

        return DB::transaction(function () use ($debitNote) {
            // Get the original supplier bill to understand the purchase structure
            $supplierBill = $debitNote->supplierBill;
            if (!$supplierBill) {
                throw new \Exception('No supplier bill found for this debit note');
            }

            // Find the original purchase journal entry
            $originalPurchaseJournal = $supplierBill->purchaseJournal;
            if (!$originalPurchaseJournal) {
                // Log a warning and abort the journal creation process
                \Log::warning("Original purchase journal not found for supplier bill #{$supplierBill->formatted_id}. Aborting journal creation for debit note #{$debitNote->debit_note_number}.");
                throw new \Exception('Original purchase journal not found. Cannot create reverse journal entry.');
            }

            $lines = [];
            $totalAmount = round($debitNote->total_amount, 2);

            // ========================================
            // REVERSE THE ORIGINAL PURCHASE JOURNAL ENTRY
            // ========================================
            //
            // The original was Debit Inventory (1200) / Credit Accounts Payable
            // (2000): purchases land in inventory, not in an expense account, so
            // sending goods back is the mirror of that and nothing else. In
            // particular it never touches Cost of Goods Sold - stock returned to a
            // vendor was never sold.

            // 1. Reduce what is owed to the vendor.
            $lines[] = [
                'account_code' => '2000', // Accounts Payable
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => "Vendor Return - Reverse Accounts Payable #{$debitNote->debit_note_number}",
            ];

            // 2. Take the returned goods back out of inventory, at cost.
            $totalCost = round($debitNote->items->sum(function ($item) {
                return $item->quantity * ($item->product->purchase_price ?? 0);
            }), 2);

            if ($totalCost > 0) {
                $lines[] = [
                    'account_code' => '1200', // Inventory
                    'debit' => 0,
                    'credit' => $totalCost,
                    'description' => "Vendor Return - Inventory Reduction #{$debitNote->debit_note_number}",
                ];
            }

            // 3. Anything credited to the vendor beyond the cost of the goods -
            // or short of it - is a price adjustment rather than a stock movement,
            // so it goes to Purchase Returns and keeps the entry balanced.
            $difference = round($totalAmount - $totalCost, 2);

            if ($difference != 0.0) {
                $lines[] = [
                    'account_code' => '5100', // Purchase Returns (Contra Expense)
                    'debit' => $difference < 0 ? abs($difference) : 0,
                    'credit' => $difference > 0 ? $difference : 0,
                    'description' => "Vendor Return - Purchase Returns #{$debitNote->debit_note_number}",
                ];
            }

            // Create journal entry with draft status (no immediate financial impact)
            $journalEntry = $this->accountingService->post(
                $lines,
                $debitNote->issue_date,
                "Vendor Return - Reverse Purchase Journal #{$debitNote->debit_note_number}",
                $debitNote,
                'draft'
            );

            // Update the journal entry with reverse journal metadata
            $journalEntry->update([
                'is_reverse' => true,
                'reverses_journal_id' => $originalPurchaseJournal->id,
                'linked_debit_note_id' => $debitNote->id,
            ]);

            // Update the debit note with journal entry reference
            $debitNote->update(['journal_entry_id' => $journalEntry->id]);
            $debitNote->setRelation('journalEntry', $journalEntry);

            // Log the creation with reverse logic details
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'vendor_return_journal_created',
                'description' => "Vendor return journal entry #{$journalEntry->formatted_id} created with built-in reverse logic for debit note #{$debitNote->debit_note_number}. Original purchase journal: #{$originalPurchaseJournal->formatted_id}",
                'subject_type' => $debitNote->getMorphClass(),
                'subject_id' => $debitNote->getKey(),
                'metadata' => [
                    'reverse_logic_applied' => true,
                    'original_purchase_bill' => $supplierBill->formatted_id,
                    'original_purchase_journal' => $originalPurchaseJournal->formatted_id,
                    'reverses_journal_id' => $originalPurchaseJournal->id,
                    'linked_debit_note_id' => $debitNote->id,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Approve a customer return journal entry (draft -> approved)
     *
     * Approval is review only. The entry still carries no financial impact until
     * it is posted, which keeps return entries on the same draft -> approved ->
     * posted path as every other entry in the ledger.
     */
    public function approveCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        $journalEntry = $creditNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this credit note');
        }

        return DB::transaction(function () use ($journalEntry, $creditNote) {
            $this->accountingService->approveEntry($journalEntry);

            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'customer_return_journal_approved',
                'description' => "Customer return journal entry #{$journalEntry->formatted_id} approved for credit note #{$creditNote->credit_note_number}. Not yet on the ledger.",
                'subject_type' => $creditNote->getMorphClass(),
                'subject_id' => $creditNote->getKey(),
            ]);

            return $journalEntry->fresh();
        });
    }

    /**
     * Post a customer return journal entry (change status from approved to posted)
     * This triggers financial statement impact
     */
    public function postCustomerReturnJournal(CreditNote $creditNote): JournalEntry
    {
        $journalEntry = $creditNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this credit note');
        }

        if (! $journalEntry->canBePosted()) {
            throw new \InvalidArgumentException('Journal entry must be approved before it can be posted');
        }

        return DB::transaction(function () use ($journalEntry, $creditNote) {
            // Post the journal entry
            $this->accountingService->postJournalEntry($journalEntry);

            // Calculate financial statement impact
            $financialImpact = $this->calculateFinancialStatementImpact($journalEntry, 'customer_return');

            // Log the posting with reverse logic confirmation and financial impact details
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'customer_return_journal_posted',
                'description' => "Customer return journal entry #{$journalEntry->formatted_id} posted with built-in reverse logic for credit note #{$creditNote->credit_note_number}",
                'subject_type' => $creditNote->getMorphClass(),
                'subject_id' => $creditNote->getKey(),
                'metadata' => [
                    'reverse_logic_applied' => true,
                    'financial_impact' => 'now_active',
                    'trial_balance_impact' => $financialImpact['trial_balance'],
                    'income_statement_impact' => $financialImpact['income_statement'],
                    'balance_sheet_impact' => $financialImpact['balance_sheet'],
                    'cash_flow_impact' => $financialImpact['cash_flow'],
                    'total_refund' => $creditNote->total_amount,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Approve a vendor return journal entry (draft -> approved)
     *
     * Review only; the ledger is untouched until the entry is posted.
     */
    public function approveVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        $journalEntry = $debitNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this debit note');
        }

        return DB::transaction(function () use ($journalEntry, $debitNote) {
            $this->accountingService->approveEntry($journalEntry);

            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'vendor_return_journal_approved',
                'description' => "Vendor return journal entry #{$journalEntry->formatted_id} approved for debit note #{$debitNote->debit_note_number}. Not yet on the ledger.",
                'subject_type' => $debitNote->getMorphClass(),
                'subject_id' => $debitNote->getKey(),
            ]);

            return $journalEntry->fresh();
        });
    }

    /**
     * Post a vendor return journal entry (change status from approved to posted)
     * This triggers financial statement impact
     */
    public function postVendorReturnJournal(DebitNote $debitNote): JournalEntry
    {
        $journalEntry = $debitNote->journalEntry;
        if (!$journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this debit note');
        }

        if (! $journalEntry->canBePosted()) {
            throw new \InvalidArgumentException('Journal entry must be approved before it can be posted');
        }

        return DB::transaction(function () use ($journalEntry, $debitNote) {
            // Post the journal entry
            $this->accountingService->postJournalEntry($journalEntry);

            // Calculate financial statement impact
            $financialImpact = $this->calculateFinancialStatementImpact($journalEntry, 'vendor_return');

            // Log the posting with reverse logic confirmation and financial impact details
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'vendor_return_journal_posted',
                'description' => "Vendor return journal entry #{$journalEntry->formatted_id} posted with built-in reverse logic for debit note #{$debitNote->debit_note_number}",
                'subject_type' => $debitNote->getMorphClass(),
                'subject_id' => $debitNote->getKey(),
                'metadata' => [
                    'reverse_logic_applied' => true,
                    'financial_impact' => 'now_active',
                    'trial_balance_impact' => $financialImpact['trial_balance'],
                    'income_statement_impact' => $financialImpact['income_statement'],
                    'balance_sheet_impact' => $financialImpact['balance_sheet'],
                    'cash_flow_impact' => $financialImpact['cash_flow'],
                    'total_amount' => $debitNote->total_amount,
                ],
            ]);

            return $journalEntry;
        });
    }

    /**
     * Calculate the financial statement impact of posting a return journal entry
     */
    private function calculateFinancialStatementImpact(JournalEntry $journalEntry, string $returnType): array
    {
        $impact = [
            'trial_balance' => [],
            'income_statement' => [],
            'balance_sheet' => [],
            'cash_flow' => [],
        ];

        foreach ($journalEntry->lines as $line) {
            $accountCode = $line->account->code;
            $amount = $line->debit > 0 ? $line->debit : $line->credit;
            $type = $line->debit > 0 ? 'debit' : 'credit';

            // Trial Balance Impact (all accounts)
            $impact['trial_balance'][] = [
                'account' => $line->account->name,
                'account_code' => $accountCode,
                'amount' => $amount,
                'type' => $type,
                'description' => $line->description,
            ];

            // Income Statement Impact (revenue and expense accounts)
            if (in_array($accountCode, ['4000', '4100', '4200', '4300', '4400', '4500', '4600', '4700', '4800', '4900', // Revenue accounts
                                       '5000', '5100', '5200', '5300', '5400', '5500', '5600', '5700', '5800', '5900'])) { // Expense accounts
                $impact['income_statement'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $type,
                    'description' => $line->description,
                    'effect' => $this->getIncomeStatementEffect($accountCode, $type, $returnType),
                ];
            }

            // Balance Sheet Impact (asset, liability, equity accounts)
            if (in_array($accountCode, ['1000', '1100', '1200', '1300', '1400', '1500', '1600', '1700', '1800', '1900', // Asset accounts
                                       '2000', '2100', '2200', '2300', '2400', '2500', '2600', '2700', '2800', '2900', // Liability accounts
                                       '3000', '3100', '3200', '3300', '3400', '3500', '3600', '3700', '3800', '3900'])) { // Equity accounts
                $impact['balance_sheet'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $type,
                    'description' => $line->description,
                    'effect' => $this->getBalanceSheetEffect($accountCode, $type, $returnType),
                ];
            }

            // Cash Flow Impact (cash and cash-equivalent accounts)
            if (in_array($accountCode, ['1000', '1100'])) { // Cash and Accounts Receivable
                $impact['cash_flow'][] = [
                    'account' => $line->account->name,
                    'account_code' => $accountCode,
                    'amount' => $amount,
                    'type' => $type,
                    'description' => $line->description,
                    'activity' => 'operating',
                    'effect' => $this->getCashFlowEffect($accountCode, $type, $returnType),
                ];
            }
        }

        return $impact;
    }

    /**
     * Get the income statement effect description
     */
    private function getIncomeStatementEffect(string $accountCode, string $type, string $returnType): string
    {
        if (in_array($accountCode, ['5200', '5100'])) { // Sales Returns & Allowances, Purchase Returns
            return $returnType === 'customer_return' 
                ? 'Reduces revenue (contra revenue account)'
                : 'Reduces expenses (contra expense account)';
        }

        if ($accountCode === '5000') { // Cost of Goods Sold
            return $type === 'credit' 
                ? 'Reduces cost of goods sold (increases gross profit)'
                : 'Increases cost of goods sold (decreases gross profit)';
        }

        return 'Affects net income calculation';
    }

    /**
     * Get the balance sheet effect description
     */
    private function getBalanceSheetEffect(string $accountCode, string $type, string $returnType): string
    {
        if ($accountCode === '1100') { // Accounts Receivable
            return $type === 'credit' 
                ? 'Reduces accounts receivable (customer owes less)'
                : 'Increases accounts receivable (customer owes more)';
        }

        if ($accountCode === '2000') { // Accounts Payable
            return $type === 'debit'
                ? 'Reduces accounts payable (owe vendor less)'
                : 'Increases accounts payable (owe vendor more)';
        }

        if ($accountCode === '1200') { // Inventory
            return $type === 'debit' 
                ? 'Increases inventory (more stock available)'
                : 'Decreases inventory (less stock available)';
        }

        return 'Affects balance sheet position';
    }

    /**
     * Get the cash flow effect description
     */
    private function getCashFlowEffect(string $accountCode, string $type, string $returnType): string
    {
        if ($accountCode === '1000') { // Cash
            return $type === 'debit' 
                ? 'Increases cash (cash inflow)'
                : 'Decreases cash (cash outflow)';
        }

        if ($accountCode === '1100') { // Accounts Receivable
            return $type === 'credit' 
                ? 'Reduces accounts receivable (improves cash flow)'
                : 'Increases accounts receivable (reduces cash flow)';
        }

        return 'Affects operating cash flow';
    }

    /**
     * Get detailed reverse logic explanation for a journal entry
     */
    public function getReverseLogicExplanation(JournalEntry $journalEntry): array
    {
        $explanation = [
            'journal_entry_id' => $journalEntry->formatted_id,
            'reverse_logic_applied' => true,
            'original_transaction' => null,
            'reverse_entries' => [],
            'financial_impact' => null,
        ];

        // Determine the type of return based on the source
        if ($journalEntry->source_type === CreditNote::class) {
            $creditNote = $journalEntry->source;
            $explanation['type'] = 'customer_return';
            $explanation['original_transaction'] = [
                'type' => 'sales_invoice',
                'number' => $creditNote->invoice->invoice_number ?? 'Unknown',
                'amount' => $creditNote->total_amount,
            ];
            $explanation['reverse_entries'] = [
                'Sales Revenue (4000) → Sales Returns & Allowances (5200) - Debit',
                'Accounts Receivable (1100) → Accounts Receivable (1100) - Credit',
                'Inventory (1200) → Inventory (1200) - Debit (if applicable)',
                'Cost of Goods Sold (5000) → Cost of Goods Sold (5000) - Credit (if applicable)',
            ];
            
            if ($journalEntry->status === 'posted') {
                $explanation['financial_impact'] = $this->calculateFinancialStatementImpact($journalEntry, 'customer_return');
            }
        } elseif ($journalEntry->source_type === DebitNote::class) {
            $debitNote = $journalEntry->source;
            $explanation['type'] = 'vendor_return';
            $explanation['original_transaction'] = [
                'type' => 'purchase_bill',
                'number' => $debitNote->supplierBill->formatted_id ?? 'Unknown',
                'amount' => $debitNote->total_amount,
            ];
            $explanation['reverse_entries'] = [
                'Accounts Payable (2000) - Debit (reduces what is owed to the vendor)',
                'Inventory (1200) - Credit (goods leave stock at cost)',
                'Purchase Returns (5100) - Debit or Credit (only the price difference, if any)',
            ];
            
            if ($journalEntry->status === 'posted') {
                $explanation['financial_impact'] = $this->calculateFinancialStatementImpact($journalEntry, 'vendor_return');
            }
        }

        return $explanation;
    }

    /**
     * Validate that a journal entry follows proper reverse logic
     */
    public function validateReverseLogic(JournalEntry $journalEntry): bool
    {
        // Basic validation - ensure the journal entry is balanced
        if (!$journalEntry->isBalanced()) {
            return false;
        }

        // Check if it's a return journal entry
        if (!in_array($journalEntry->source_type, [CreditNote::class, DebitNote::class])) {
            return false;
        }

        // Validate account codes are appropriate for return type
        $accountCodes = $journalEntry->lines->pluck('account.code')->toArray();
        
        if ($journalEntry->source_type === CreditNote::class) {
            // Customer return should have Sales Returns & Allowances (5200) and Accounts Receivable (1100)
            $requiredAccounts = ['5200', '1100'];
            if (!array_intersect($accountCodes, $requiredAccounts)) {
                return false;
            }
        } elseif ($journalEntry->source_type === DebitNote::class) {
            // Vendor return should reduce Accounts Payable (2000) and Inventory (1200)
            $requiredAccounts = ['2000', '1200'];
            if (!array_intersect($accountCodes, $requiredAccounts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get summary of financial statement impact for a posted return journal
     */
    public function getFinancialImpactSummary(JournalEntry $journalEntry): array
    {
        if ($journalEntry->status !== 'posted') {
            return [
                'status' => 'draft',
                'message' => 'Journal entry is in draft status - no financial impact yet',
            ];
        }

        $returnType = $journalEntry->source_type === CreditNote::class ? 'customer_return' : 'vendor_return';
        $impact = $this->calculateFinancialStatementImpact($journalEntry, $returnType);

        $summary = [
            'status' => 'posted',
            'return_type' => $returnType,
            'total_impact' => [
                'trial_balance_entries' => count($impact['trial_balance']),
                'income_statement_entries' => count($impact['income_statement']),
                'balance_sheet_entries' => count($impact['balance_sheet']),
                'cash_flow_entries' => count($impact['cash_flow']),
            ],
            'key_effects' => [],
        ];

        // Add key effects based on return type
        if ($returnType === 'customer_return') {
            $summary['key_effects'] = [
                'Revenue reduced via Sales Returns & Allowances',
                'Accounts Receivable reduced',
                'Inventory increased (if applicable)',
                'Cost of Goods Sold reduced (if applicable)',
            ];
        } else {
            $summary['key_effects'] = [
                'Accounts Payable reduced',
                'Inventory decreased at cost (if applicable)',
                'Purchase Returns adjusted for any price difference',
            ];
        }

        return $summary;
    }
} 