<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of prices that applies to somebody, for a while.
 *
 * Two types share the table. A 'sale' list is what we charge - retail,
 * wholesale, a promotion, one customer's negotiated rate. A 'purchase' list is
 * what we are charged, one per vendor. They are the same shape and the same
 * resolution rules, so they are one mechanism rather than two.
 *
 * Who a list applies to is decided by its assignments; a list with none applies
 * to everyone. See [PriceListAssignment].
 */
class PriceList extends Model
{
    use HasFactory;

    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';

    protected $fillable = [
        'name',
        'code',
        'type',
        'currency',
        'priority',
        'is_default',
        'is_active',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PriceListAssignment::class);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |---------------------------------------------------------------------*/

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Lists that are switched on and whose own season covers $at.
     *
     * A list with no starts_at/ends_at runs indefinitely; that is the ordinary
     * case, and a promotion is the exception that fills them in.
     */
    public function scopeActiveAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }
}
