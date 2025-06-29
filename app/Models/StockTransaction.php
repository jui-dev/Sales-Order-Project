<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\HasFormattedId;

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
    public const TYPE_ADJUSTMENT        = 'adjustment';        // Manual adjustments

    /**
     * Quick lookup array for validation.
     */
    public const VALID_TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_STOCK_TRANSFER,
        self::TYPE_ORDER_FULFILLMENT,
        self::TYPE_ADJUSTMENT,
    ];

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
        'transaction_date' => 'date',
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
            default                      => [
                'badge_color' => 'secondary',
                'icon'        => 'bi bi-pencil',
                'label'       => ucfirst($this->transaction_type ?? 'Adj'),
                'description' => 'Adjustment',
            ],
        };
    }

    public function getFormattedQuantity(): string
    {
        return number_format(abs($this->quantity));
    }

    public function getEffectAttribute(): int
    {
        return $this->direction === 'inbound' ? 1 : -1;
    }

    public function getEffectDescription(): string
    {
        return $this->direction === 'inbound' ? 'Stock ↑' : 'Stock ↓';
    }

    /**
     * Provide a human readable label for the reference (supply/order/etc.).
     */
    public function getReferenceDisplay(): array
    {
        $label = match ($this->reference_type) {
            \App\Models\Supply::class         => 'Supply',
            \App\Models\Order::class          => 'Order',
            \App\Models\StockTransfer::class  => 'Transfer',
            default                            => class_basename($this->reference_type),
        };

        return [
            'label' => $label,
        ];
    }

    /**
     * Stub accessors for source/destination locations so Blade conditional checks work.
     */
    public function getSourceLocationAttribute()
    {
        return null;
    }

    public function getDestinationLocationAttribute()
    {
        return null;
    }

    // ------------------------------------------------------------------
    // Alias accessors for legacy Blade templates
    // ------------------------------------------------------------------
    public function getMovementDateAttribute()
    {
        return $this->transaction_date ?? $this->created_at;
    }

    public function getMovementTypeAttribute()
    {
        return $this->transaction_type;
    }
} 