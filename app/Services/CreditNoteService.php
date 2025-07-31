<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\StockTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Traits\HasErrorHandling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CreditNoteService
{
    use HasErrorHandling;

    /**
     * Get all credit notes with pagination and filtering
     */
    public function getAllCreditNotes(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = CreditNote::with(['customer', 'invoice', 'returnTransaction.product', 'journalEntry'])
                    ->latest();

                // Apply filters
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function($q) use ($search) {
                        $q->where('credit_note_number', 'like', "%{$search}%")
                          ->orWhere('reference_number', 'like', "%{$search}%")
                          ->orWhere('reason', 'like', "%{$search}%")
                          ->orWhereHas('customer', function($customerQuery) use ($search) {
                              $customerQuery->where('name', 'like', "%{$search}%");
                          });
                    });
                }

                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['customer_id'])) {
                    $query->where('customer_id', $filters['customer_id']);
                }

                if (!empty($filters['date_from'])) {
                    $query->where('issue_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $query->where('issue_date', '<=', $filters['date_to']);
                }

                return $query->paginate($perPage);
            },
            'credit_notes',
            $perPage,
            $filters
        );
    }

    /**
     * Get credit note by ID with all related data
     */
    public function getCreditNote(int $id): CreditNote
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $creditNote = CreditNote::with(['customer', 'invoice', 'returnTransaction.product', 'journalEntry'])
                    ->find($id);
                
                if (!$creditNote) {
                    $this->logMissingData('credit_note', $id);
                    throw new \App\Exceptions\DataNotFoundException('credit_note', $id);
                }
                
                return $creditNote;
            },
            'credit_note',
            $id
        );
    }

    /**
     * Get filter options for credit notes
     */
    public function getFilterOptions(): array
    {
        return [
            'statuses' => [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'posted' => 'Posted',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ],
            'customers' => \App\Models\Customer::orderBy('name')->pluck('name', 'id')->toArray(),
        ];
    }

    /**
     * Get sort options for credit notes
     */
    public function getSortOptions(): array
    {
        return [
            'issue_date' => 'Issue Date',
            'credit_note_number' => 'Credit Note Number',
            'total_amount' => 'Total Amount',
            'status' => 'Status',
            'customer_name' => 'Customer Name',
        ];
    }

    /**
     * Get statistics for credit notes dashboard
     */
    public function getStatistics(): array
    {
        return $this->handleServiceOperation(
            function() {
                $total = CreditNote::count();
                $pending = CreditNote::where('status', 'pending')->count();
                $issued = CreditNote::where('status', 'issued')->count();
                $totalAmount = CreditNote::where('status', 'issued')->sum('total_amount');

                return [
                    'total' => $total,
                    'pending' => $pending,
                    'issued' => $issued,
                    'total_amount' => $totalAmount,
                ];
            },
            'credit_note_statistics'
        );
    }

    /**
     * Generate credit note for a customer return
     */
    public function generateCreditNote(StockTransaction $returnTransaction): CreditNote
    {
        return $this->handleServiceOperation(
            function() use ($returnTransaction) {
                if ($returnTransaction->transaction_type !== StockTransaction::TYPE_CUSTOMER_RETURN) {
                    throw new \InvalidArgumentException('Credit notes can only be generated for customer returns');
                }

                if ($returnTransaction->status !== 'approved') {
                    throw new \InvalidArgumentException('Credit notes can only be generated for approved returns');
                }

                return DB::transaction(function () use ($returnTransaction) {
                    // Get the invoice from the return transaction
                    $invoice = $returnTransaction->invoice;
                    if (!$invoice) {
                        throw new \Exception('No invoice found for this return transaction');
                    }

                    // Get the original invoice item for this product
                    $invoiceItem = InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('product_id', $returnTransaction->product_id)
                        ->first();

                    if (!$invoiceItem) {
                        throw new \Exception('Original invoice item not found for this product');
                    }

                    // Calculate credit amount (use original invoice price)
                    $creditAmount = $invoiceItem->unit_price * $returnTransaction->quantity;
                    $subtotal = $creditAmount;
                    $taxAmount = 0; // Calculate based on original invoice tax
                    $discountAmount = 0; // Calculate based on original invoice discount

                    // Create credit note with comprehensive data
                    $creditNote = CreditNote::create([
                        'invoice_id' => $invoice->id,
                        'customer_id' => $invoice->customer_id,
                        'return_transaction_id' => $returnTransaction->id,
                        'total_amount' => $creditAmount,
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'discount_amount' => $discountAmount,
                        'status' => 'issued',
                        'issue_date' => now(),
                        'reason' => 'Customer Return',
                        'notes' => "Credit note for return transaction #{$returnTransaction->formatted_id}",
                        'currency' => 'USD',
                        'exchange_rate' => 1.0000,
                        'remaining_amount' => $creditAmount,
                        'applied_amount' => 0,
                        'total_amount_base_currency' => $creditAmount,
                        'reference_number' => $invoice->invoice_number,
                        'created_by' => Auth::id(),
                        'metadata' => [
                            'product_id' => $returnTransaction->product_id,
                            'product_name' => $returnTransaction->product->name,
                            'product_sku' => $returnTransaction->product->sku,
                            'quantity_returned' => $returnTransaction->quantity,
                            'original_unit_price' => $invoiceItem->unit_price,
                            'invoice_item_id' => $invoiceItem->id,
                            'return_reason' => $returnTransaction->notes,
                        ],
                    ]);

                    // Create credit note item
                    $creditNote->items()->create([
                        'product_id' => $returnTransaction->product_id,
                        'invoice_item_id' => $invoiceItem->id,
                        'quantity' => $returnTransaction->quantity,
                        'unit_price' => $invoiceItem->unit_price,
                        'total_amount' => $creditAmount,
                        'tax_rate' => $invoiceItem->tax_rate ?? 0,
                        'tax_amount' => $taxAmount,
                        'discount_rate' => $invoiceItem->discount_rate ?? 0,
                        'discount_amount' => $discountAmount,
                        'subtotal' => $subtotal,
                        'description' => $invoiceItem->description ?? $returnTransaction->product->name,
                        'notes' => $returnTransaction->notes,
                        'sku' => $returnTransaction->product->sku,
                        'product_name' => $returnTransaction->product->name,
                        'product_metadata' => [
                            'original_invoice_item_id' => $invoiceItem->id,
                            'return_transaction_id' => $returnTransaction->id,
                        ],
                        'created_by' => Auth::id(),
                    ]);

                    return $creditNote->load(['customer', 'invoice', 'returnTransaction.product', 'journalEntry', 'items.product']);
                });
            },
            'credit_note'
        );
    }

    /**
     * Apply credit note to an invoice
     */
    public function applyCreditNote(CreditNote $creditNote, Invoice $invoice, float $amount, string $notes = null): bool
    {
        if (!$creditNote->canBeApplied()) {
            throw new \InvalidArgumentException('Credit note cannot be applied');
        }

        if ($amount > $creditNote->remaining_amount) {
            throw new \InvalidArgumentException('Amount exceeds remaining credit note amount');
        }

        return DB::transaction(function () use ($creditNote, $invoice, $amount, $notes) {
            // Update credit note amounts
            $creditNote->update([
                'remaining_amount' => $creditNote->remaining_amount - $amount,
                'applied_amount' => $creditNote->applied_amount + $amount,
                'updated_by' => Auth::id(),
            ]);

            // Update metadata to track applications
            $metadata = $creditNote->metadata ?? [];
            $applications = $metadata['applications'] ?? [];
            $applications[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount_applied' => $amount,
                'applied_at' => now()->toISOString(),
                'notes' => $notes,
                'applied_by' => Auth::id(),
            ];
            $metadata['applications'] = $applications;
            
            $creditNote->update(['metadata' => $metadata]);

            return true;
        });
    }

    /**
     * Cancel a credit note
     */
    public function cancelCreditNote(CreditNote $creditNote, string $reason = null): CreditNote
    {
        if ($creditNote->status === 'cancelled') {
            throw new \InvalidArgumentException('Credit note is already cancelled');
        }

        if ($creditNote->applied_amount > 0) {
            throw new \InvalidArgumentException('Cannot cancel credit note that has been applied');
        }

        $creditNote->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
            'updated_by' => Auth::id(),
        ]);

        return $creditNote;
    }

    /**
     * Get credit note statistics
     */
    public function getCreditNoteStatistics(): array
    {
        return [
            'total' => CreditNote::count(),
            'pending' => CreditNote::where('status', 'pending')->count(),
            'issued' => CreditNote::where('status', 'issued')->count(),
            'cancelled' => CreditNote::where('status', 'cancelled')->count(),
            'expired' => CreditNote::where('status', 'expired')->count(),
            'total_amount' => CreditNote::where('status', 'issued')->sum('total_amount'),
            'remaining_amount' => CreditNote::where('status', 'issued')->sum('remaining_amount'),
            'applied_amount' => CreditNote::where('status', 'issued')->sum('applied_amount'),
        ];
    }

    /**
     * Get available credit notes for a customer
     */
    public function getAvailableCreditNotesForCustomer(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return CreditNote::where('customer_id', $customerId)
            ->where('status', 'issued')
            ->where('remaining_amount', '>', 0)
            ->orderBy('issue_date', 'desc')
            ->get();
    }

    /**
     * Post credit note (create journal entry)
     */
    public function postCreditNote(CreditNote $creditNote): CreditNote
    {
        if (!$creditNote->canBePosted()) {
            throw new \InvalidArgumentException('Credit note cannot be posted');
        }

        return DB::transaction(function () use ($creditNote) {
            // Create journal entry with draft status using ReturnJournalHandler
            $journalEntry = app(ReturnJournalHandler::class)->createCustomerReturnJournal($creditNote);
            
            // Update credit note status from 'issued' to 'posted'
            $creditNote->update([
                'status' => CreditNote::STATUS_POSTED,
                'updated_by' => Auth::id(),
            ]);
            
            return $creditNote->load('journalEntry');
        });
    }

    /**
     * Post journal entry (change from draft to posted)
     */
    public function postJournalEntry(CreditNote $creditNote): CreditNote
    {
        if (!$creditNote->journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this credit note');
        }

        if ($creditNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Journal entry is already posted');
        }

        // Post the journal entry using ReturnJournalHandler
        app(ReturnJournalHandler::class)->postCustomerReturnJournal($creditNote);

        return $creditNote->load('journalEntry');
    }
} 