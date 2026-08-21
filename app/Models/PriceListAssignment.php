<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * What makes a price list apply to somebody.
 *
 * Points at a Customer, CustomerGroup, SalesChannel, Warehouse, Retailer or
 * Vendor. A list carrying no assignments at all applies to everyone - that is
 * the difference between the base retail list and a negotiated one.
 *
 * Polymorphic so that scoping prices by something the system does not have yet
 * is a new row, not a new column.
 */
class PriceListAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'assignable_type',
        'assignable_id',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
