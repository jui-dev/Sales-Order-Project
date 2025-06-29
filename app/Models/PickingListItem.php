<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickingListItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'picking_list_id',
        'product_id',
        'quantity_requested',
        'quantity_picked',
        'status',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_picked'    => 'integer',
    ];

    /**
     * Convenient accessor for compatibility – maps old `quantity` property
     * to the new `quantity_requested` column so legacy code won\'t break.
     */
    public function getQuantityAttribute(): int
    {
        return $this->quantity_requested;
    }

    /**
     * Mutator for legacy `quantity` assignment that writes to `quantity_requested`.
     */
    public function setQuantityAttribute($value): void
    {
        $this->attributes['quantity_requested'] = $value;
    }

    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
} 