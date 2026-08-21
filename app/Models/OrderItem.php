<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'location_id',
        'location_type',
        'quantity',
        'unit_price',
        'unit_cost',
        'subtotal',
    ];

    /**
     * unit_cost is a real column now, so only profit needs appending.
     */
    protected $appends = [
        'profit',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withDefault();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }

    public function location(): MorphTo
    {
        return $this->morphTo();
    }

    /* ------------------------------------------------------------------
     | Dynamic Attributes
     |------------------------------------------------------------------*/

    /**
     * What this line cost us when it was sold.
     *
     * Deliberately does NOT fall back to the product's current purchase_price.
     * That fallback is what let a later goods receipt rewrite the profit on
     * orders that were closed months earlier. A line with no captured cost
     * reads as 0 and is treated as "unknown", not as "today's cost".
     */
    public function getUnitCostAttribute(): float
    {
        return (float) ($this->attributes['unit_cost'] ?? 0);
    }

    public function getProfitAttribute(): float
    {
        $unitCost = $this->unit_cost;
        return round(($this->unit_price - $unitCost) * $this->quantity, 2);
    }
} 