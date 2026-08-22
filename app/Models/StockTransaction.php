<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\HasFormattedId;
use App\Models\Grn;
use Illuminate\Support\Facades\DB;

class StockTransaction extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'TXN';

    protected $fillable = [
        'product_id',
        'location_id',
        'location_type',
        'quantity',
        'direction',
        'transaction_type',
        'reference_type',
        'reference_id',
        'transaction_date',
        'status',
        'notes',
        'stock_posted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'return_data',
    ];

    /**
     * ---------------------------------------------------------------------
     * Transaction Type Constants
     * ---------------------------------------------------------------------
     *
     * Centralised list of supported transaction types. Whenever you need to
     * reference a type, reference one of these constants instead of hard
     * coding strings. This helps to avoid typos and ensures we only persist
     * recognised values in the database.
     */
    public const TYPE_STOCK_IN          = 'stock_in';          // Vendor → Warehouse (inbound)
    public const TYPE_STOCK_TRANSFER    = 'stock_transfer';    // Warehouse ⇄ Retailer (inbound / outbound)
    public const TYPE_ORDER_FULFILLMENT = 'order_fulfillment'; // Customer orders (outbound)
    public const TYPE_CUSTOMER_RETURN   = 'customer_return';   // Customer returns (inbound)
    public const TYPE_VENDOR_RETURN     = 'vendor_return';     // Vendor returns (outbound)
    public const TYPE_RETAILER_RETURN   = 'retailer_return';   // Retailer returns (inbound)
    public const TYPE_ADJUSTMENT        = 'adjustment';        // Manual adjustments

    /**
     * Quick lookup array for validation.
     */
    public const VALID_TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_STOCK_TRANSFER,
        self::TYPE_ORDER_FULFILLMENT,
        self::TYPE_CUSTOMER_RETURN,
        self::TYPE_VENDOR_RETURN,
        self::TYPE_RETAILER_RETURN,
        self::TYPE_ADJUSTMENT,
    ];

    /**
     * Return type constants for easy reference
     */
    public const RETURN_TYPES = [
        self::TYPE_CUSTOMER_RETURN,
        self::TYPE_VENDOR_RETURN,
        self::TYPE_RETAILER_RETURN,
    ];

    /**
     * Status constants for all transactions including returns
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // GRN Status Constants
    public const GRN_STATUS_DRAFT = 'draft';
    public const GRN_STATUS_POSTED = 'posted';

    /**
     * Ensure an invalid/empty transaction_type never slips into the DB.
     * If an invalid value is supplied we default to "adjustment" so the
     * record is still persisted while remaining semantically correct.
     */
    protected static function booted(): void
    {
        static::saving(function (self $txn) {
            if (empty($txn->transaction_type) || ! in_array($txn->transaction_type, self::VALID_TYPES, true)) {
                $txn->transaction_type = self::TYPE_ADJUSTMENT;
            }
        });
    }

    protected $casts = [
        'transaction_date' => 'datetime',
        'status' => 'string',
        'stock_posted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'return_data' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }

    public function location(): MorphTo
    {
        return $this->morphTo();
    }

    public function stockLocation(): MorphTo
    {
        // Alias that matches naming used in Blade (stockLocation)
        return $this->morphTo('location');
    }

    // Accessor so $transaction->stockLocation works even if relation not eager loaded
    public function getStockLocationAttribute()
    {
        return $this->relationLoaded('stockLocation') ? $this->getRelation('stockLocation') : $this->location;
    }

    /**
     * Reference relationships for returns
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convenience methods to access specific reference types
     * These use the morphTo relationship and filter by reference_type
     */
    public function getInvoiceAttribute()
    {
        return $this->reference_type === Invoice::class ? $this->reference : null;
    }

    public function getSupplierBillAttribute()
    {
        return $this->reference_type === SupplierBill::class ? $this->reference : null;
    }

    public function getStockTransferAttribute()
    {
        return $this->reference_type === StockTransfer::class ? $this->reference : null;
    }

    /**
     * Return-specific relationships
     */
    /**
     * Helper methods to access return-specific data
     */
    public function getReturnReasonAttribute()
    {
        if (!$this->isReturn()) {
            return null;
        }

        // For returns, the notes field now directly contains the return reason
        return $this->notes;
    }

    /**
     * approved_by/at, rejected_by/at, completed_by/at and cancelled_by/at are
     * real columns now, so they need no accessors. Only the values that live
     * inside the return_data payload are read through one.
     */
    public function getReturnAmountAttribute()
    {
        $returnData = $this->return_data;
        return (is_array($returnData) && isset($returnData['return_amount'])) ? (float) $returnData['return_amount'] : 0.0;
    }

    public function getRejectionReasonAttribute()
    {
        $returnData = $this->return_data;
        return (is_array($returnData) && isset($returnData['rejection_reason'])) ? $returnData['rejection_reason'] : null;
    }

    public function getCancellationNotesAttribute()
    {
        $returnData = $this->return_data;
        return (is_array($returnData) && isset($returnData['cancellation_notes'])) ? $returnData['cancellation_notes'] : null;
    }

    /**
     * The note raised from this return when it was approved. Only one of the
     * two is ever populated, decided by the return type.
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class, 'return_transaction_id');
    }

    public function debitNote(): HasOne
    {
        return $this->hasOne(DebitNote::class, 'return_transaction_id');
    }

    /**
     * Users behind the return audit trail.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * These accessors avoid crashes in the Blade if columns/relations are missing.
     */
    public function getUnitCostAttribute($value)
    {
        return $value ?? 0.0;
    }

    public function getTotalCostAttribute($value)
    {
        return $value ?? ($this->unit_cost * abs($this->quantity));
    }

    /* ------------------------------------------------------------------
     | Return-specific helper methods
     |------------------------------------------------------------------*/

    /**
     * Check if this is a return transaction
     */
    public function isReturn(): bool
    {
        return in_array($this->transaction_type, self::RETURN_TYPES);
    }

    /**
     * Check if this is a customer return
     */
    public function isCustomerReturn(): bool
    {
        return $this->transaction_type === self::TYPE_CUSTOMER_RETURN;
    }

    /**
     * Check if this is a vendor return
     */
    public function isVendorReturn(): bool
    {
        return $this->transaction_type === self::TYPE_VENDOR_RETURN;
    }

    /**
     * Check if this is a retailer return
     */
    public function isRetailerReturn(): bool
    {
        return $this->transaction_type === self::TYPE_RETAILER_RETURN;
    }

    /**
     * Status helper methods
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ISSUED => 'info',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_COMPLETED => 'info',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get return type label for UI
     */
    public function getReturnTypeLabel(): string
    {
        return match($this->transaction_type) {
            self::TYPE_CUSTOMER_RETURN => 'Customer Return',
            self::TYPE_VENDOR_RETURN => 'Vendor Return',
            self::TYPE_RETAILER_RETURN => 'Retailer Return',
            default => 'Unknown',
        };
    }

    /**
     * Get return type badge class for UI
     */
    public function getReturnTypeBadgeClass(): string
    {
        return match($this->transaction_type) {
            self::TYPE_CUSTOMER_RETURN => 'danger',
            self::TYPE_VENDOR_RETURN => 'info',
            self::TYPE_RETAILER_RETURN => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Business logic methods for return management
     */
    public function approve(int $approvedByUserId, ?string $notes = null): void
    {
        if (!$this->isReturn()) {
            throw new \InvalidArgumentException('Only return transactions can be approved');
        }

        // For retailer returns, allow approval from issued status
        if ($this->isRetailerReturn()) {
            if (!$this->isIssued() && !$this->isPending()) {
                throw new \InvalidArgumentException('Only issued or pending retailer returns can be approved');
            }
        } else {
            if (!$this->isPending()) {
                throw new \InvalidArgumentException('Only pending returns can be approved');
            }
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedByUserId,
            'approved_at' => now(),
        ]);

        // The single point at which a return moves stock. StockTransactionObserver
        // deliberately skips returns, and stock_posted_at makes this idempotent.
        $this->updateProductStock();

        // Generate appropriate notes based on return type
        $this->generateReturnNotes();

        // Log the approval
        $this->logStatusChange('approved', $notes);
    }

    public function reject(int $rejectedByUserId, string $reason): void
    {
        if (!$this->isReturn()) {
            throw new \InvalidArgumentException('Only return transactions can be rejected');
        }

        // Retailer returns open at "issued" rather than "pending", so they are
        // rejectable from either state - matching the rule in approve().
        if ($this->isRetailerReturn()) {
            if (!$this->isIssued() && !$this->isPending()) {
                throw new \InvalidArgumentException('Only issued or pending retailer returns can be rejected');
            }
        } else {
            if (!$this->isPending()) {
                throw new \InvalidArgumentException('Only pending returns can be rejected');
            }
        }

        $returnData = is_array($this->return_data) ? $this->return_data : [];
        $returnData['rejection_reason'] = $reason;

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $rejectedByUserId,
            'rejected_at' => now(),
            'return_data' => $returnData,
        ]);

        // No stock to reverse: a return only posts stock once approved.

        // Log the rejection
        $this->logStatusChange('rejected', $reason);
    }

    public function complete(int $completedByUserId, ?string $notes = null): void
    {
        if (!$this->isReturn()) {
            throw new \InvalidArgumentException('Only return transactions can be completed');
        }

        if (!$this->isApproved()) {
            throw new \InvalidArgumentException('Only approved returns can be completed');
        }

        // Stock was already posted at approval; completing is a status change only.
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_by' => $completedByUserId,
            'completed_at' => now(),
        ]);

        // Log the completion
        $this->logStatusChange('completed', $notes);
    }

    public function cancel(int $cancelledByUserId, ?string $notes = null): void
    {
        if (!$this->isReturn()) {
            throw new \InvalidArgumentException('Only return transactions can be cancelled');
        }

        // Retailer returns open at "issued" rather than "pending".
        if ($this->isRetailerReturn()) {
            if (!$this->isIssued() && !$this->isPending()) {
                throw new \InvalidArgumentException('Only issued or pending retailer returns can be cancelled');
            }
        } else {
            if (!$this->isPending()) {
                throw new \InvalidArgumentException('Only pending returns can be cancelled');
            }
        }

        $returnData = is_array($this->return_data) ? $this->return_data : [];
        $returnData['cancellation_notes'] = $notes;

        // Cancellation details go to return_data. They used to be json_encode'd
        // into `notes`, which is where the return reason lives - overwriting it.
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_by' => $cancelledByUserId,
            'cancelled_at' => now(),
            'return_data' => $returnData,
        ]);

        // Log the cancellation
        $this->logStatusChange('cancelled', $notes);
    }

    /**
     * Update product stock based on transaction
     * Note: Vendor locations are external and should not be tracked in product_stocks table
     */
    public function updateProductStock(): void
    {
        // A transaction moves stock exactly once, ever. This is recorded on the
        // row itself rather than in the cache: the previous cache key expired
        // after an hour, after which the same transaction posted again.
        if ($this->stock_posted_at) {
            return;
        }

        // For vendor returns, we don't create product_stocks records for vendors
        // Vendors are external entities and should not be tracked in internal inventory
        if ($this->transaction_type === self::TYPE_VENDOR_RETURN) {
            // Vendor returns should only affect warehouse stock
            // Find the warehouse from the transaction location
            $warehouse = null;

            if ($this->location_type === Warehouse::class) {
                $warehouse = Warehouse::find($this->location_id);
            }

            if ($warehouse) {
                // Update warehouse stock in product_stocks table
                $productStock = ProductStock::firstOrCreate([
                    'product_id' => $this->product_id,
                    'location_id' => $warehouse->id,
                    'location_type' => Warehouse::class,
                ], [
                    'quantity' => 0,
                ]);

                $this->assertSufficientStock($productStock, $warehouse);

                // Decrease warehouse stock for vendor returns
                $productStock->decrement('quantity', $this->quantity);
                $this->markStockPosted();
            }

            return;
        }

        // For retailer returns, handle both source and destination
        if ($this->transaction_type === self::TYPE_RETAILER_RETURN) {
            // Get the stock transfer to find the warehouse destination
            $stockTransfer = $this->stockTransfer;
            if ($stockTransfer) {
                // Manually load the warehouse based on from_location_id and from_location_type
                $warehouse = null;
                if ($stockTransfer->from_location_type === 'App\\Models\\Warehouse' || $stockTransfer->from_location_type === 'App\Models\Warehouse') {
                    $warehouse = \App\Models\Warehouse::find($stockTransfer->from_location_id);
                }

                if ($warehouse) {
                    // Decrease stock from retailer (source)
                    $retailerStock = ProductStock::firstOrCreate([
                        'product_id' => $this->product_id,
                        'location_id' => $this->location_id,
                        'location_type' => $this->location_type,
                    ], [
                        'quantity' => 0,
                    ]);

                    $this->assertSufficientStock($retailerStock, $this->location);

                    $retailerStock->decrement('quantity', $this->quantity);

                    // Increase stock to warehouse (destination)
                    $warehouseStock = ProductStock::firstOrCreate([
                        'product_id' => $this->product_id,
                        'location_id' => $warehouse->id,
                        'location_type' => get_class($warehouse),
                    ], [
                        'quantity' => 0,
                    ]);
                    $warehouseStock->increment('quantity', $this->quantity);

                    $this->markStockPosted();
                } else {
                    // Log error if warehouse is not found
                    \Log::error('Warehouse not found for retailer return stock adjustment', [
                        'transaction_id' => $this->id,
                        'stock_transfer_id' => $stockTransfer->id,
                        'from_location_type' => $stockTransfer->from_location_type,
                        'from_location_id' => $stockTransfer->from_location_id
                    ]);
                }
            } else {
                // Log error if stock transfer is not found
                \Log::error('Stock transfer not found for retailer return stock adjustment', [
                    'transaction_id' => $this->id,
                    'reference_id' => $this->reference_id
                ]);
            }
            return;
        }

        // For all other transaction types, create/update product_stocks records
        // Only for internal locations (warehouses and retailers)
        if (in_array($this->location_type, [Warehouse::class, Retailer::class])) {
            // Handle stock adjustments based on return type
            // For vendor returns, we don't create product_stocks records for vendors
            if ($this->transaction_type === self::TYPE_VENDOR_RETURN) {
                return;
            }

            // For stock_in transactions from GRN, check GRN status
            if ($this->isGrnBasedStockIn()) {
                $grn = Grn::find($this->reference_id);

                // Only update stock if GRN is posted
                if (!$grn || $grn->status !== self::GRN_STATUS_POSTED) {
                    return;
                }
            }

            // Calculate the stock change based on direction
            $stockChange = $this->quantity;
            if (in_array($this->direction, ['out', 'outbound'])) {
                $stockChange = -$stockChange;
            }

            // Use database locking to prevent race conditions and ensure atomic operations
            DB::transaction(function() use ($stockChange) {
                // Lock the ProductStock record for update to prevent race conditions
                $productStock = ProductStock::where('product_id', $this->product_id)
                    ->where('location_id', $this->location_id)
                    ->where('location_type', $this->location_type)
                    ->lockForUpdate()
                    ->first();

                if (!$productStock) {
                    // Create new record if it doesn't exist
                    $productStock = ProductStock::create([
                        'product_id' => $this->product_id,
                        'location_id' => $this->location_id,
                        'location_type' => $this->location_type,
                        'quantity' => $stockChange, // Set to the change amount directly
                    ]);
                } else {
                    // Update existing record
                    $productStock->quantity += $stockChange;
                    $productStock->save();
                }

                // Mark this transaction as processed to prevent double updates
                $this->markStockPosted();
            });
        }
    }

    /**
     * Record that this transaction's stock effect has been applied, so it can
     * never be applied a second time. Saved quietly so it does not re-enter
     * StockTransactionObserver::updated().
     */
    private function markStockPosted(): void
    {
        $this->forceFill(['stock_posted_at' => now()])->saveQuietly();
    }

    /**
     * Guard an outbound movement against driving a location negative.
     * ProductStock::decrement() writes raw SQL with no floor, and the column is
     * not unsigned, so without this a return can silently go below zero.
     */
    private function assertSufficientStock(ProductStock $productStock, $location): void
    {
        if ($productStock->quantity >= $this->quantity) {
            return;
        }

        $locationName = $location->name ?? class_basename($location ?? 'location');

        throw new \RuntimeException(sprintf(
            'Cannot return %d unit(s) of %s from %s: only %d in stock.',
            $this->quantity,
            $this->product->name ?? "product #{$this->product_id}",
            $locationName,
            $productStock->quantity
        ));
    }

    /**
     * Generate appropriate return notes based on type
     */
    private function generateReturnNotes(): void
    {
        switch ($this->transaction_type) {
            case self::TYPE_CUSTOMER_RETURN:
                $this->generateCreditNote();
                break;
            case self::TYPE_VENDOR_RETURN:
                $this->generateDebitNote();
                break;
            case self::TYPE_RETAILER_RETURN:
                // No note is raised - nothing is owed either way - but the value
                // still has to move back between the inventory locations that
                // the original transfer moved it out of.
                $this->generateRetailerReturnJournal();
                break;
        }
    }

    /**
     * Generate credit note for customer returns.
     *
     * The failure is logged and then rethrown. It used to be swallowed, which
     * left an approved return with its stock already moved and no credit note
     * anywhere - and the screen still said the approval had succeeded.
     * ReturnService::approveReturn() runs this inside a transaction, so letting
     * it out rolls back the status and the stock together.
     */
    private function generateCreditNote(): void
    {
        try {
            $creditNoteService = app(\App\Services\CreditNoteService::class);
            $creditNoteService->generateCreditNote($this);
        } catch (\Exception $e) {
            \Log::error('Failed to generate credit note for return: ' . $e->getMessage(), [
                'return_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Move the inventory value back between stock locations for a
     * retailer return. Rethrows for the same reason as generateCreditNote().
     */
    private function generateRetailerReturnJournal(): void
    {
        try {
            app(\App\Services\ReturnJournalHandler::class)->createRetailerReturnJournal($this);
        } catch (\Exception $e) {
            \Log::error('Failed to generate retailer return journal: ' . $e->getMessage(), [
                'return_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate debit note for vendor returns. Rethrows for the same reason as
     * generateCreditNote() above.
     */
    private function generateDebitNote(): void
    {
        try {
            $debitNoteService = app(\App\Services\DebitNoteService::class);
            $debitNoteService->generateDebitNote($this);
        } catch (\Exception $e) {
            \Log::error('Failed to generate debit note for return: ' . $e->getMessage(), [
                'return_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    // Internal return note generation removed - no longer needed

    /**
     * Log status changes for audit trail
     */
    private function logStatusChange(string $action, ?string $notes = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action' => "return_{$action}",
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'description' => "Return #{$this->formatted_id} was {$action}" . ($notes ? ": {$notes}" : ''),
            'old_values' => null,
            'new_values' => ['status' => $this->status],
        ]);
    }

    /* ------------------------------------------------------------------
     | Display helpers used by the Blade
     |------------------------------------------------------------------*/
    public function getDisplayConfig(): array
    {
        return match ($this->transaction_type) {
            self::TYPE_STOCK_IN          => [
                'badge_color' => 'success',
                'icon'        => 'bi bi-box-arrow-in-down',
                'label'       => 'Stock In',
                'description' => 'Vendor → Warehouse',
            ],
            self::TYPE_STOCK_TRANSFER    => [
                'badge_color' => 'primary',
                'icon'        => 'bi bi-arrow-left-right',
                'label'       => 'Transfer',
                'description' => 'Internal movement',
            ],
            self::TYPE_ORDER_FULFILLMENT => [
                'badge_color' => 'warning',
                'icon'        => 'bi bi-truck',
                'label'       => 'Order',
                'description' => ($this->location_type === \App\Models\Retailer::class)
                    ? 'Retailer → Customer'
                    : 'Warehouse → Customer',
            ],
            self::TYPE_CUSTOMER_RETURN   => [
                'badge_color' => 'danger',
                'icon'        => 'bi bi-arrow-return-left',
                'label'       => 'Customer Return',
                'description' => 'Customer → ' . class_basename($this->location_type),
            ],
            self::TYPE_VENDOR_RETURN     => [
                'badge_color' => 'info',
                'icon'        => 'bi bi-arrow-return-right',
                'label'       => 'Vendor Return',
                'description' => 'Warehouse → Vendor',
            ],
            self::TYPE_RETAILER_RETURN   => [
                'badge_color' => 'warning',
                'icon'        => 'bi bi-arrow-return-left',
                'label'       => 'Retailer Return',
                'description' => 'Retailer → Warehouse',
            ],
            self::TYPE_ADJUSTMENT        => [
                'badge_color' => 'secondary',
                'icon'        => 'bi bi-slash-circle',
                'label'       => 'Adjustment',
                'description' => 'Manual stock adjustment',
            ],
            default                      => [
                'badge_color' => 'secondary',
                'icon'        => 'bi bi-question-circle',
                'label'       => 'Unknown',
                'description' => 'Unknown transaction type',
            ],
        };
    }

    public function getMovementDateAttribute()
    {
        return $this->transaction_date ?? $this->created_at;
    }

    public function getMovementTypeAttribute()
    {
        return $this->transaction_type;
    }

    /**
     * Get reference display information for UI
     */
    public function getReferenceDisplay(): array
    {
        if (!$this->reference_type) {
            return ['label' => 'No Reference'];
        }

        return match($this->reference_type) {
            'App\Models\Order' => ['label' => 'Order'],
            'App\Models\Supply' => ['label' => 'Supply'],
            'App\Models\StockTransfer' => ['label' => 'Transfer'],
            'App\Models\Invoice' => ['label' => 'Invoice'],
            'App\Models\SupplierBill' => ['label' => 'Supplier Bill'],
            'App\Models\CreditNote' => ['label' => 'Credit Note'],
            'App\Models\DebitNote' => ['label' => 'Debit Note'],
            default => ['label' => class_basename($this->reference_type)],
        };
    }

    /**
     * Get formatted quantity with proper sign
     */
    public function getFormattedQuantity(): string
    {
        $quantity = abs($this->quantity);
        $sign = $this->isInboundDirection() ? '+' : '-';
        return $sign . $quantity;
    }

    /**
     * Get effect description for UI
     */
    public function getEffectDescription(): string
    {
        return match($this->direction) {
            'in', 'inbound' => 'Stock In',
            'out', 'outbound' => 'Stock Out',
            default => 'Unknown',
        };
    }

    /**
     * Get detailed effect description based on transaction type
     */
    public function getDetailedEffectDescription(): string
    {
        return match($this->transaction_type) {
            self::TYPE_STOCK_IN => 'Vendor → Warehouse',
            self::TYPE_STOCK_TRANSFER => 'Internal Transfer',
            self::TYPE_ORDER_FULFILLMENT => 'Customer Order',
            self::TYPE_CUSTOMER_RETURN => 'Customer Return',
            self::TYPE_VENDOR_RETURN => 'Vendor Return',
            self::TYPE_RETAILER_RETURN => 'Retailer Return',
            self::TYPE_ADJUSTMENT => 'Manual Adjustment',
            default => $this->getEffectDescription(),
        };
    }

    /**
     * Check if this is an inbound direction (stock coming in)
     */
    private function isInboundDirection(): bool
    {
        return in_array($this->direction, ['in', 'inbound']);
    }

    /**
     * Check if this is an outbound direction (stock going out)
     */
    private function isOutboundDirection(): bool
    {
        return in_array($this->direction, ['out', 'outbound']);
    }

    /**
     * Check if this is a stock-in transaction
     */
    public function isStockIn(): bool
    {
        return $this->transaction_type === self::TYPE_STOCK_IN;
    }

    /**
     * Check if this is a GRN-based stock-in transaction
     */
    public function isGrnBasedStockIn(): bool
    {
        return $this->isStockIn() && $this->reference_type === Grn::class;
    }

    /**
     * Get return source information for display
     */
    public function getReturnSourceInfo(): array
    {
        // Ensure we always return an array
        if (!$this->isReturn()) {
            return [
                'source' => 'Not a Return',
                'reference' => 'N/A',
                'date' => 'N/A'
            ];
        }

        try {
            $reference = $this->reference;
            if (!$reference) {
                return [
                    'source' => 'Unknown Source',
                    'reference' => 'No Reference',
                    'date' => 'Unknown Date'
                ];
            }

            switch ($this->transaction_type) {
                case self::TYPE_CUSTOMER_RETURN:
                    if ($reference instanceof \App\Models\Invoice) {
                        $invoiceDate = $reference->invoice_date;
                        $formattedDate = 'Unknown Date';
                        if ($invoiceDate) {
                            if (is_string($invoiceDate)) {
                                $formattedDate = \Carbon\Carbon::parse($invoiceDate)->format('M d, Y');
                            } else {
                                $formattedDate = $invoiceDate->format('M d, Y');
                            }
                        }

                        return [
                            'source' => $reference->customer->name ?? 'Unknown Customer',
                            'reference' => $reference->formatted_id ?? 'Invoice #' . $reference->id,
                            'date' => $formattedDate
                        ];
                    }
                    break;

                case self::TYPE_VENDOR_RETURN:
                    if ($reference instanceof \App\Models\SupplierBill) {
                        // For vendor returns, the source is the warehouse (where stock is decreased from)
                        // Get the warehouse from the supplier bill's GRN's supply
                        $warehouse = $reference->grn->supply->warehouse ?? null;
                        $sourceName = $warehouse ? $warehouse->name : 'Unknown Warehouse';

                        $billDate = $reference->bill_date;
                        $formattedDate = 'Unknown Date';
                        if ($billDate) {
                            if (is_string($billDate)) {
                                $formattedDate = \Carbon\Carbon::parse($billDate)->format('M d, Y');
                            } else {
                                $formattedDate = $billDate->format('M d, Y');
                            }
                        }

                        return [
                            'source' => $sourceName,
                            'reference' => $reference->formatted_id ?? 'Bill #' . $reference->id,
                            'date' => $formattedDate
                        ];
                    }
                    break;

                case self::TYPE_RETAILER_RETURN:
                    if ($reference instanceof \App\Models\StockTransfer) {
                        $fromLocation = $reference->fromLocation;
                        $transferDate = $reference->transfer_date;
                        $formattedDate = 'Unknown Date';
                        if ($transferDate) {
                            if (is_string($transferDate)) {
                                $formattedDate = \Carbon\Carbon::parse($transferDate)->format('M d, Y');
                            } else {
                                $formattedDate = $transferDate->format('M d, Y');
                            }
                        }

                        return [
                            'source' => $fromLocation ? $fromLocation->name : 'Unknown Retailer',
                            'reference' => $reference->formatted_id ?? 'Transfer #' . $reference->id,
                            'date' => $formattedDate
                        ];
                    }
                    break;
            }

            // Fallback for unknown reference types
            $transactionDate = $this->transaction_date;
            $formattedDate = 'Unknown Date';
            if ($transactionDate) {
                if (is_string($transactionDate)) {
                    $formattedDate = \Carbon\Carbon::parse($transactionDate)->format('M d, Y');
                } else {
                    $formattedDate = $transactionDate->format('M d, Y');
                }
            }

            return [
                'source' => 'Unknown Source',
                'reference' => 'Reference #' . $this->reference_id,
                'date' => $formattedDate
            ];

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in getReturnSourceInfo for transaction ' . $this->id . ': ' . $e->getMessage());

            return [
                'source' => 'Error Loading Source',
                'reference' => 'Reference #' . $this->reference_id,
                'date' => 'Unknown Date'
            ];
        }
    }

    /**
     * Get reference number for display
     */
    public function getReferenceNumberAttribute(): string
    {
        if (!$this->reference_type || !$this->reference_id) {
            return 'N/A';
        }

        // Try to get the reference model and its formatted ID
        try {
            $reference = $this->reference;
            if ($reference && method_exists($reference, 'getFormattedIdAttribute')) {
                return $reference->formatted_id;
            }
            return '#' . $this->reference_id;
        } catch (\Exception $e) {
            return '#' . $this->reference_id;
        }
    }

    /**
     * Get effect value for UI (positive for in, negative for out)
     */
    public function getEffectAttribute(): int
    {
        return $this->isInboundDirection() ? $this->quantity : -$this->quantity;
    }
}
