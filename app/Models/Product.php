<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Product extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'PRD';

    protected $fillable = [
        'id',
        'name',
        'sku',
        'description',
        'selling_price',
        'purchase_price',
        'gross_profit',
        'profit_margin',
        'auto_pricing_enabled',
        'available_stocks',
        'last_price_update',
    ];

    /**
     * Getter for backward compatibility: treat quantity as available_stocks.
     */
    public function getQuantityAttribute(): ?int
    {
        return $this->available_stocks;
    }

    /**
     * Setter for backward compatibility: allow mass-assigning quantity.
     */
    public function setQuantityAttribute($value): void
    {
        $this->attributes['available_stocks'] = $value;
    }

    public function supplyItems(): HasMany
    {
        return $this->hasMany(\App\Models\SupplyItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(\App\Models\ProductStock::class);
    }

    /* ---------------------------------------------------------------------
     | Computed Attributes
     |---------------------------------------------------------------------*/

    /**
     * Dynamically expose a "gp" attribute (Gross Profit percentage).
     * The percentage is shown ONLY when the product has at least one
     * completed order. For products without a completed sale, this
     * accessor returns null so the UI can gracefully hide it.
     */
    public function getGpAttribute(): ?float
    {
        if (! $this->hasCompletedOrders()) {
            return null;
        }

        // Prefer the persisted profit_margin column if present
        if ($this->profit_margin !== null) {
            return (float) $this->profit_margin;
        }

        // Fallback: calculate on-the-fly
        if ($this->purchase_price && $this->selling_price) {
            return round((($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100, 2);
        }

        return null;
    }

    /**
     * Determine if the product has at least one completed order.
     */
    public function hasCompletedOrders(): bool
    {
        return $this->orderItems()
            ->whereHas('order', fn ($q) => $q->where('status', 'completed'))
            ->exists();
    }

    /**
     * Helper to fetch the stock balance for this product at a specific location.
     * This is primarily used by Blade templates when rendering picking or
     * stock-analysis pages.
     *
     * @param  int         $locationId
     * @param  string|null $locationType   Optional – if omitted will match any type.
     * @return \App\Models\ProductStock  A ProductStock model (may be a fresh instance with zero quantities)
     */
    public function getStockAtLocation(int $locationId, ?string $locationType = null): \App\Models\ProductStock
    {
        $query = \App\Models\ProductStock::where('product_id', $this->id)
            ->where('location_id', $locationId);

        if ($locationType !== null) {
            $query->where('location_type', $locationType);
        }

        /** @var \App\Models\ProductStock|null $stock */
        $stock = $query->first();

        if (! $stock) {
            // Provide a lightweight placeholder so callers can still access
            // ->available_quantity, ->reserved_quantity, etc. without errors.
            $stock = new \App\Models\ProductStock([
                'product_id'    => $this->id,
                'location_id'   => $locationId,
                'location_type' => $locationType,
                'quantity'      => 0,
            ]);
        }

        // Computed/alias attributes expected by some Blade files.
        $stock->current_stock    = $stock->quantity;
        $stock->reserved_quantity = 0; // Reservation system not yet implemented.
        $stock->available_quantity = $stock->quantity - $stock->reserved_quantity;

        return $stock;
    }

    // ------------------------------------------------------------------
    // Dynamic Stock Level Accessors
    // ------------------------------------------------------------------
    public function getWarehouseStockAttribute(): int
    {
        return (int) $this->stockBalances()->where('location_type', 'warehouse')->sum('quantity');
    }

    public function getRetailerStockAttribute(): int
    {
        return (int) $this->stockBalances()->where('location_type', 'retailer')->sum('quantity');
    }

    public function getLocationsCountAttribute(): int
    {
        return (int) $this->stockBalances()->count('location_id');
    }

    public function getAvailableStocksAttribute($value): int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return (int) $this->stockBalances()->sum('quantity');
    }
} 