<?php

namespace App\Models;

use App\Models\Traits\HasFormattedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a warehouse asked a vendor to send.
 *
 * Sits in front of Supply in the procurement chain:
 * Purchase Order -> Record Supply -> Receive Goods -> Supplier Bill -> Payment.
 * Nothing here touches stock or pricing; an order is a statement of intent
 * until a GRN is posted.
 */
class PurchaseOrder extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'PO';

    /** Being drafted; lines can still change. */
    public const STATUS_DRAFT = 'draft';

    /** Signed off internally, not yet with the vendor. */
    public const STATUS_APPROVED = 'approved';

    /** With the vendor, awaiting delivery. */
    public const STATUS_SENT = 'sent';

    /** Some but not all of the ordered quantity has arrived. */
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    /** Everything ordered has arrived. */
    public const STATUS_RECEIVED = 'received';

    /** Abandoned; will never be received. */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    protected $fillable = [
        'vendor_id',
        'warehouse_id',
        'status',
        'expected_date',
        'total_cost',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'approved_at' => 'datetime',
        'total_cost' => 'decimal:2',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withDefault();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Lines can only be edited while the order is still a draft - once it has
     * been approved the figures are what was signed off on.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * A supply can only be recorded against an order that is actually with the
     * vendor, and that still has something outstanding.
     */
    public function isReceivable(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_PARTIALLY_RECEIVED,
        ], true);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Whether any ordered quantity is still outstanding.
     */
    public function hasOutstandingItems(): bool
    {
        return $this->items->contains(fn (PurchaseOrderItem $item) => $item->outstanding() > 0);
    }
}
