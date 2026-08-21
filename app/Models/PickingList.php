<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class PickingList extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'PL';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'picker_id',
        'picking_number',
        'from_location_id',
        'from_location_type',
        'to_location_id',
        'to_location_type',
        'status',
        'picking_date',
        'completed_at',
    ];

    protected $casts = [
        'picking_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PickingListItem::class);
    }

    public function pickingItems(): HasMany
    {
        // Alias for items() relationship expected by some Blade views
        return $this->items();
    }

    /**
     * Determine if the picking list has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Accessor: total requested items.
     */
    public function getTotalItemsAttribute(): int
    {
        // Defer to relationship count if loaded, otherwise run an aggregate query.
        return $this->relationLoaded('items')
            ? $this->items->sum('quantity_requested')
            : (int) $this->items()->sum('quantity_requested');
    }

    /**
     * Accessor: picked/fulfilled items. (Placeholder logic for UI-only phase)
     */
    public function getPickedItemsAttribute(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->sum('quantity_picked')
            : (int) $this->items()->sum('quantity_picked');
    }

    /**
     * Accessor: progress percentage (0-100) for UI progress bar.
     */
    public function getProgressPercentageAttribute(): float
    {
        // Shortcut: if list already flagged completed / closed / verified, return 100% for UI consistency
        if (in_array($this->status, ['completed', 'closed', 'verified'], true)) {
            return 100.0;
        }

        if ($this->total_items === 0) {
            return 0.0;
        }

        return round(($this->picked_items / $this->total_items) * 100, 2);
    }

    public function fromLocation(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'from_location_type', 'from_location_id');
    }

    public function toLocation(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'to_location_type', 'to_location_id');
    }

    // Accessor aliases expected by legacy Blade templates
    public function getFromLocationAttribute()
    {
        return $this->fromLocation()->withDefault()->getResults();
    }

    public function getToLocationAttribute()
    {
        return $this->toLocation()->withDefault()->getResults();
    }

    /**
     * What kind of pick this is - derived, never stored.
     *
     * Several screens want to badge a list as "Warehouse To Customer" or
     * "Retailer To Customer", and used to read a picking_type column. There has
     * never been one: no migration creates it, so the reads all came back null
     * and the badges rendered blank. The one place that asked SQL for it
     * directly (ProductService) simply errored.
     *
     * It is not worth adding, because the journey already says it. A list runs
     * from somewhere to somewhere, both recorded, and the type is exactly that
     * pair - which is how the index pages actually filter. Storing it as well
     * would be a second answer to a question the locations already settle, free
     * to disagree with them.
     *
     * Null when either end is unknown: a half-built list has no type yet, and
     * saying so beats inventing one.
     */
    public function getPickingTypeAttribute(): ?string
    {
        if (! $this->from_location_type || ! $this->to_location_type) {
            return null;
        }

        return \Illuminate\Support\Str::snake(class_basename($this->from_location_type))
            .'_to_'
            .\Illuminate\Support\Str::snake(class_basename($this->to_location_type));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class, 'reference_id');
    }
} 