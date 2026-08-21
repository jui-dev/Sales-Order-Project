<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * What the stock on hand is worth, as at a moment in time.
 *
 * Distinct from what any vendor charges: a vendor quote is one supplier's offer
 * and lives on a purchase price list, while this is the single costing figure
 * the ledger values inventory and COGS at.
 *
 * Append-only. Each receipt adds a row carrying the moving average struck over
 * the stock then on hand, so the cost in force on any past date is still
 * recoverable - which is what stops a return or a report being re-valued by a
 * delivery that arrived after the event.
 */
class ProductCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'unit_cost',
        'quantity_on_hand',
        'effective_at',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'quantity_on_hand' => 'integer',
        'effective_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The GRN that caused this cost, where there was one. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The row in force at $at - the most recent one not in the future.
     */
    public function scopeInForceAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->orderByDesc('id');
    }
}
