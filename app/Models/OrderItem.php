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
        'subtotal',
    ];

    protected $appends = [
        'unit_cost',
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
    public function getUnitCostAttribute(): float
    {
        // If the column exists in the attributes array, return it; otherwise
        // fall back to the product's purchase price at the time of rendering.
        if (array_key_exists('unit_cost', $this->attributes)) {
            return (float) $this->attributes['unit_cost'];
        }

        return (float) ($this->product->purchase_price ?? 0);
    }

    public function getProfitAttribute(): float
    {
        $unitCost = $this->unit_cost;
        return round(($this->unit_price - $unitCost) * $this->quantity, 2);
    }
} 