<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class ProductStock extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'PST';

    protected $fillable = [
        'product_id',
        'location_id',
        'location_type',
        'quantity',
        'reserved_quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor aliases expected by Blade views
     */
    public function getCurrentStockAttribute(): int
    {
        return (int) $this->quantity;
    }

    public function getReservedQuantityAttribute(): int
    {
        return (int) ($this->attributes['reserved_quantity'] ?? 0);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->current_stock - $this->reserved_quantity);
    }

    public function getStockLocationAttribute()
    {
        // Provide convenient alias used by Blade templates.
        return $this->relationLoaded('location') ? $this->getRelation('location') : $this->location;
    }
} 