<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class CreditNote extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'CN';

    protected $fillable = [
        'formatted_id',
        'credit_note_number',
        'status',
        'issue_date',
        'expiry_date',
        'customer_id',
        'invoice_id',
        'return_transaction_id',
        'journal_entry_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'remaining_amount',
        'applied_amount',
        'currency',
        'exchange_rate',
        'total_amount_base_currency',
        'reference_number',
        'reason',
        'notes',
        'terms_and_conditions',
        'is_partial',
        'is_recurring',
        'recurring_frequency',
        'next_recurring_date',
        'metadata',
        'tax_breakdown',
        'discount_breakdown',
        'created_by',
        'updated_by',
        'approved_by',
        'cancelled_by',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'expiry_date' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'next_recurring_date' => 'date',
        'is_partial' => 'boolean',
        'is_recurring' => 'boolean',
        'metadata' => 'array',
        'tax_breakdown' => 'array',
        'discount_breakdown' => 'array',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'total_amount_base_currency' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_ISSUED = 'issued';
    const STATUS_POSTED = 'posted';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function returnTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'return_transaction_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Status methods
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ISSUED, self::STATUS_POSTED]);
    }

    // Financial methods
    public function getAvailableAmount(): float
    {
        return $this->remaining_amount;
    }

    public function getAppliedAmount(): float
    {
        return $this->applied_amount;
    }

    public function getTotalAmount(): float
    {
        return $this->total_amount;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getTaxAmount(): float
    {
        return $this->tax_amount;
    }

    public function getDiscountAmount(): float
    {
        return $this->discount_amount;
    }

    // Display attributes
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING => 'warning',
            self::STATUS_ISSUED => 'success',
            self::STATUS_POSTED => 'info',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_EXPIRED => 'dark',
            default => 'secondary',
        };
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return '$' . number_format($this->remaining_amount, 2);
    }

    public function getFormattedAppliedAmountAttribute(): string
    {
        return '$' . number_format($this->applied_amount, 2);
    }

    // Business logic methods
    public function canBeApplied(): bool
    {
        return $this->isIssued() && $this->remaining_amount > 0;
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive() && $this->applied_amount == 0;
    }

    public function canBePosted(): bool
    {
        return $this->isIssued() && !$this->journalEntry;
    }

    public function isAlreadyPosted(): bool
    {
        return $this->isPosted() && $this->journalEntry;
    }

    public function isFullyApplied(): bool
    {
        return $this->remaining_amount == 0;
    }

    public function isPartiallyApplied(): bool
    {
        return $this->applied_amount > 0 && $this->remaining_amount > 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ISSUED]);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeByReturnTransaction($query, $returnTransactionId)
    {
        return $query->where('return_transaction_id', $returnTransactionId);
    }

    protected static function boot()
    {
        parent::boot();

        // Numbered from the primary key, which is the only source of a unique
        // number the database will actually stand behind.
        //
        // This used to strip the digits out of the last row's number and add
        // one. Delete a note and the next one is issued the number that has
        // just been freed - on a unique column, so raising it fails outright -
        // and two notes raised at once read the same last row and collide.
        static::created(function (self $creditNote) {
            if (empty($creditNote->getRawOriginal('credit_note_number'))) {
                $creditNote->forceFill([
                    'credit_note_number' => $creditNote->generateCreditNoteNumber(),
                ])->saveQuietly();
            }
        });
    }

    /**
     * The note's own number, derived from its key.
     *
     * formatted_id is deliberately not written here. The HasFormattedId
     * accessor shadows the column of the same name, so anything stored in it
     * could never be read back again.
     */
    public function generateCreditNoteNumber(): string
    {
        return 'CN-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
} 