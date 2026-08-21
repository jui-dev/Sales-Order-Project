<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product's price on one list, for one stretch of time.
 *
 * These rows are append-only. Changing a price does not UPDATE unit_price - it
 * stamps ends_at on the standing row and inserts a replacement. That is what
 * makes "the price we charged in March" a thing the database can still answer,
 * and it is enforced by going through PriceListService rather than by writing
 * here directly.
 */
class PriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'unit_price',
        'min_quantity',
        'markup_percent',
        'basis_price_list_item_id',
        'is_auto_derived',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'min_quantity' => 'integer',
        'markup_percent' => 'decimal:2',
        'is_auto_derived' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The vendor quote this sale price was worked out from.
     *
     * Records the reasoning, not a rule: stock is pooled, so a unit on the
     * shelf has no vendor identity and the basis cannot decide what is charged.
     * It is what makes the gross profit shown beside the price mean something.
     */
    public function basis(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class, 'basis_price_list_item_id');
    }

    /** The gross profit this price earns against the basis it was set from. */
    public function grossProfit(): ?float
    {
        if (! $this->basis) {
            return null;
        }

        return round((float) $this->unit_price - (float) $this->basis->unit_price, 2);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |---------------------------------------------------------------------*/

    /**
     * Rows whose effective window covers $at.
     *
     * Written once here because the resolver and the product listing joins both
     * need exactly this predicate, and a date comparison that drifts between
     * the two would show one price and charge another.
     */
    public function scopeInForceAt(Builder $query, \DateTimeInterface $at): Builder
    {
        // Qualified: the resolver joins price_lists, which carries its own
        // starts_at/ends_at pair for seasonal lists. Unqualified names are
        // ambiguous there, and would silently test the wrong dates if the
        // driver resolved them rather than erroring.
        $table = $query->getModel()->getTable();

        return $query->where("{$table}.starts_at", '<=', $at)
            ->where(fn ($q) => $q
                ->whereNull("{$table}.ends_at")
                ->orWhere("{$table}.ends_at", '>', $at));
    }
}
