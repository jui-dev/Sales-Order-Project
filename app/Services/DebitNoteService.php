<?php

namespace App\Services;

use App\Models\DebitNote;
use App\Models\StockTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Traits\HasErrorHandling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Services\ReturnJournalHandler;

class DebitNoteService
{
    use HasErrorHandling;

    /**
     * Get all debit notes with pagination and filtering
     */
    public function getAllDebitNotes(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                try {
                    $query = DebitNote::with(['vendor', 'supplierBill', 'returnTransaction.product', 'journalEntry'])
                        ->latest();

                    // Apply filters
                    if (!empty($filters['search'])) {
                        $search = $filters['search'];
                        $query->where(function($q) use ($search) {
                            $q->where('debit_note_number', 'like', "%{$search}%")
                              ->orWhere('reference_number', 'like', "%{$search}%")
                              ->orWhere('reason', 'like', "%{$search}%")
                              ->orWhereHas('vendor', function($vendorQuery) use ($search) {
                                  $vendorQuery->where('name', 'like', "%{$search}%");
                              });
                        });
                    }

                    if (!empty($filters['status'])) {
                        $query->where('status', $filters['status']);
                    }

                    if (!empty($filters['vendor_id'])) {
                        $query->where('vendor_id', $filters['vendor_id']);
                    }

                    if (!empty($filters['date_from'])) {
                        $query->where('issue_date', '>=', $filters['date_from']);
                    }

                    if (!empty($filters['date_to'])) {
                        $query->where('issue_date', '<=', $filters['date_to']);
                    }

                    return $query->paginate($perPage);
                } catch (\Exception $e) {
                    \Log::error('Error getting debit notes: ' . $e->getMessage());
                    return DebitNote::paginate($perPage);
                }
            },
            'debit_notes',
            $perPage,
            $filters
        );
    }

    /**
     * Get debit note by ID with all related data
     */
    public function getDebitNote(int $id): DebitNote
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $debitNote = DebitNote::with(['vendor', 'supplierBill', 'returnTransaction.product', 'journalEntry'])
                    ->find($id);
                
                if (!$debitNote) {
                    $this->logMissingData('debit_note', $id);
                    throw new \App\Exceptions\DataNotFoundException('debit_note', $id);
                }
                
                return $debitNote;
            },
            'debit_note',
            $id
        );
    }

    /**
     * Get filter options for debit notes
     */
    public function getFilterOptions(): array
    {
        return [
            'statuses' => [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ],
            'vendors' => \App\Models\Vendor::orderBy('name')->pluck('name', 'id')->toArray(),
        ];
    }

    /**
     * Get sort options for debit notes
     */
    public function getSortOptions(): array
    {
        return [
            'issue_date' => 'Issue Date',
            'debit_note_number' => 'Debit Note Number',
            'total_amount' => 'Total Amount',
            'status' => 'Status',
            'vendor_name' => 'Vendor Name',
        ];
    }

    /**
     * Generate debit note for a vendor return
     */
    public function generateDebitNote(StockTransaction $returnTransaction): DebitNote
    {
        return $this->handleServiceOperation(
            function() use ($returnTransaction) {
                if ($returnTransaction->transaction_type !== StockTransaction::TYPE_VENDOR_RETURN) {
                    throw new \InvalidArgumentException('Debit notes can only be generated for vendor returns');
                }

                if ($returnTransaction->status !== 'approved') {
                    throw new \InvalidArgumentException('Debit notes can only be generated for approved returns');
                }

                return DB::transaction(function () use ($returnTransaction) {
                    // Get the supplier bill from the return transaction
                    $supplierBill = $returnTransaction->supplierBill;
                    if (!$supplierBill) {
                        throw new \Exception('No supplier bill found for this return transaction');
                    }

                    // Get the original supplier bill item for this product
                    $supplierBillItem = SupplierBillItem::where('supplier_bill_id', $supplierBill->id)
                        ->where('product_id', $returnTransaction->product_id)
                        ->first();

                    if (!$supplierBillItem) {
                        throw new \Exception('Original supplier bill item not found for this product');
                    }

                    // Validate unit price
                    if (empty($supplierBillItem->unit_price) || $supplierBillItem->unit_price <= 0) {
                        // Try to get unit price from product
                        $product = $returnTransaction->product;
                        $unitPrice = $product->cost_price ?? $product->purchase_price ?? 0;
                        
                        if ($unitPrice <= 0) {
                            throw new \Exception('Unable to determine unit price for debit note calculation. Please ensure the supplier bill item has a valid unit price or the product has a cost price.');
                        }
                        
                        // Log the fallback for audit purposes
                        \Log::info('Using product cost price as fallback for debit note calculation', [
                            'return_id' => $returnTransaction->id,
                            'product_id' => $returnTransaction->product_id,
                            'supplier_bill_item_id' => $supplierBillItem->id,
                            'fallback_price' => $unitPrice
                        ]);
                    } else {
                        $unitPrice = $supplierBillItem->unit_price;
                    }

                    // Calculate debit amount (use original supplier bill price or fallback)
                    $debitAmount = $unitPrice * $returnTransaction->quantity;
                    $subtotal = $debitAmount;
                    $taxAmount = 0; // Calculate based on original supplier bill tax
                    $discountAmount = 0; // Calculate based on original supplier bill discount

                    // Create debit note with comprehensive data
                    $debitNote = DebitNote::create([
                        'supplier_bill_id' => $supplierBill->id,
                        'vendor_id' => $supplierBill->vendor_id,
                        'return_transaction_id' => $returnTransaction->id,
                        'total_amount' => $debitAmount,
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'discount_amount' => $discountAmount,
                        'status' => 'issued',
                        'issue_date' => now(),
                        'reason' => 'Vendor Return',
                        'notes' => "Debit note for return transaction #{$returnTransaction->formatted_id}",
                        'currency' => 'USD',
                        'exchange_rate' => 1.0000,
                        'remaining_amount' => $debitAmount,
                        'applied_amount' => 0,
                        'total_amount_base_currency' => $debitAmount,
                        'reference_number' => $supplierBill->formatted_id,
                        'created_by' => Auth::id(),
                        'metadata' => [
                            'product_id' => $returnTransaction->product_id,
                            'product_name' => $returnTransaction->product->name,
                            'product_sku' => $returnTransaction->product->sku,
                            'quantity_returned' => $returnTransaction->quantity,
                            'original_unit_price' => $unitPrice,
                            'supplier_bill_item_id' => $supplierBillItem->id,
                            'return_reason' => $returnTransaction->notes,
                            'price_source' => empty($supplierBillItem->unit_price) ? 'product_fallback' : 'supplier_bill',
                        ],
                    ]);

                    // Create debit note item
                    \App\Models\DebitNoteItem::create([
                        'debit_note_id' => $debitNote->id,
                        'product_id' => $returnTransaction->product_id,
                        'supplier_bill_item_id' => $supplierBillItem->id,
                        'quantity' => $returnTransaction->quantity,
                        'unit_price' => $unitPrice,
                        'total_amount' => $debitAmount,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'discount_rate' => 0,
                        'discount_amount' => 0,
                        'subtotal' => $debitAmount,
                        'description' => "Return transaction #{$returnTransaction->formatted_id}",
                        'notes' => $returnTransaction->notes,
                        'sku' => $returnTransaction->product->sku,
                        'product_name' => $returnTransaction->product->name,
                        'product_metadata' => [
                            'return_transaction_id' => $returnTransaction->id,
                            'return_reason' => $returnTransaction->notes,
                            'price_source' => empty($supplierBillItem->unit_price) ? 'product_fallback' : 'supplier_bill',
                        ],
                        'created_by' => Auth::id(),
                    ]);

                    return $debitNote->load(['vendor', 'supplierBill', 'returnTransaction.product', 'journalEntry', 'items.product']);
                });
            },
            'debit_note'
        );
    }

    /**
     * Apply debit note to a supplier bill
     */
    public function applyDebitNote(DebitNote $debitNote, SupplierBill $supplierBill, float $amount, string $notes = null): bool
    {
        if (!$debitNote->canBeApplied()) {
            throw new \InvalidArgumentException('Debit note cannot be applied');
        }

        if ($amount > $debitNote->remaining_amount) {
            throw new \InvalidArgumentException('Amount exceeds remaining debit note amount');
        }

        return DB::transaction(function () use ($debitNote, $supplierBill, $amount, $notes) {
            // Update debit note amounts
            $debitNote->update([
                'remaining_amount' => $debitNote->remaining_amount - $amount,
                'applied_amount' => $debitNote->applied_amount + $amount,
                'updated_by' => Auth::id(),
            ]);

            // Update metadata to track applications
            $metadata = $debitNote->metadata ?? [];
            $applications = $metadata['applications'] ?? [];
            $applications[] = [
                'supplier_bill_id' => $supplierBill->id,
                'bill_number' => $supplierBill->formatted_id,
                'amount_applied' => $amount,
                'applied_at' => now()->toISOString(),
                'notes' => $notes,
                'applied_by' => Auth::id(),
            ];
            $metadata['applications'] = $applications;
            
            $debitNote->update(['metadata' => $metadata]);

            return true;
        });
    }

    /**
     * Cancel a debit note
     */
    public function cancelDebitNote(DebitNote $debitNote, string $reason = null): DebitNote
    {
        if ($debitNote->status === 'cancelled') {
            throw new \InvalidArgumentException('Debit note is already cancelled');
        }

        if ($debitNote->applied_amount > 0) {
            throw new \InvalidArgumentException('Cannot cancel debit note that has been applied');
        }

        $debitNote->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
            'updated_by' => Auth::id(),
        ]);

        return $debitNote;
    }

    /**
     * Get debit note statistics
     */
    public function getDebitNoteStatistics(): array
    {
        try {
            return [
                'total' => DebitNote::count(),
                'pending' => DebitNote::where('status', 'pending')->count(),
                'issued' => DebitNote::where('status', 'issued')->count(),
                'cancelled' => DebitNote::where('status', 'cancelled')->count(),
                'expired' => DebitNote::where('status', 'expired')->count(),
                'total_amount' => DebitNote::where('status', 'issued')->sum('total_amount') ?? 0,
                'remaining_amount' => DebitNote::where('status', 'issued')->sum('remaining_amount') ?? 0,
                'applied_amount' => DebitNote::where('status', 'issued')->sum('applied_amount') ?? 0,
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting debit note statistics: ' . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'issued' => 0,
                'cancelled' => 0,
                'expired' => 0,
                'total_amount' => 0,
                'remaining_amount' => 0,
                'applied_amount' => 0,
            ];
        }
    }

    /**
     * Get available debit notes for a vendor
     */
    public function getAvailableDebitNotesForVendor(int $vendorId): \Illuminate\Database\Eloquent\Collection
    {
        return DebitNote::where('vendor_id', $vendorId)
            ->where('status', 'issued')
            ->where('remaining_amount', '>', 0)
            ->orderBy('issue_date', 'desc')
            ->get();
    }

    /**
     * Post debit note (create journal entry)
     */
    public function postDebitNote(DebitNote $debitNote): DebitNote
    {
        if (!$debitNote->canBePosted()) {
            throw new \InvalidArgumentException('Debit note cannot be posted');
        }

        return DB::transaction(function () use ($debitNote) {
            // Create journal entry with draft status using ReturnJournalHandler
            $journalEntry = app(ReturnJournalHandler::class)->createVendorReturnJournal($debitNote);
            
            // Update debit note status from 'issued' to 'posted'
            $debitNote->update([
                'status' => DebitNote::STATUS_POSTED,
                'updated_by' => Auth::id(),
            ]);
            
            return $debitNote->load('journalEntry');
        });
    }

    /**
     * Post journal entry (change from draft to posted)
     */
    public function postJournalEntry(DebitNote $debitNote): DebitNote
    {
        if (!$debitNote->journalEntry) {
            throw new \InvalidArgumentException('No journal entry found for this debit note');
        }

        if ($debitNote->journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Journal entry is already posted');
        }

        // Post the journal entry using ReturnJournalHandler
        app(ReturnJournalHandler::class)->postVendorReturnJournal($debitNote);

        return $debitNote->load('journalEntry');
    }
} 