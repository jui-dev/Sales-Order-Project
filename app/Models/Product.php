<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'category_id',
        'selling_price',
        'purchase_price',
        'gross_profit',
        'markup',
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

    // ReturnItems relationship removed - no longer needed

    public function stockBalances(): HasMany
    {
        return $this->hasMany(\App\Models\ProductStock::class);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(\App\Models\StockTransaction::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductCategory::class, 'category_id');
    }

    /* ---------------------------------------------------------------------
     | Computed Attributes
     |---------------------------------------------------------------------*/

    /**
     * Dynamically expose a "gp" attribute (Gross Profit amount).
     * The gross profit is shown ONLY when the product has at least one
     * confirmed order. For products without a confirmed sale, this
     * accessor returns null so the UI can gracefully hide it.
     */
    public function getGpAttribute(): ?float
    {
        if (! $this->hasConfirmedOrders()) {
            return null;
        }

        // Return the stored gross_profit amount
        if ($this->gross_profit !== null) {
            return (float) $this->gross_profit;
        }

        // Fallback: calculate on-the-fly if we have both prices
        if ($this->purchase_price && $this->selling_price) {
            return round($this->selling_price - $this->purchase_price, 2);
        }

        return null;
    }

    /**
     * Determine if the product has at least one confirmed order.
     */
    public function hasConfirmedOrders(): bool
    {
        return $this->orderItems()
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['confirmed', 'completed']))
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
        // Get stock from ProductStock table for the specific location
        $query = \App\Models\ProductStock::where('product_id', $this->id)
            ->where('location_id', $locationId);

        if ($locationType !== null) {
            $query->where('location_type', $locationType);
        }

        $stock = $query->first();

        if (!$stock) {
            // Create a ProductStock model with 0 values if not found
            $stock = new \App\Models\ProductStock([
                'product_id'    => $this->id,
                'location_id'   => $locationId,
                'location_type' => $locationType,
                'quantity'      => 0,
                'reserved_quantity' => 0,
            ]);
        }

        // Computed/alias attributes expected by some Blade files.
        $stock->current_stock = $stock->quantity;
        $stock->available_quantity = $stock->quantity - $stock->reserved_quantity;

        return $stock;
    }

    // ------------------------------------------------------------------
    // Dynamic Stock Level Accessors
    // ------------------------------------------------------------------
    public function getWarehouseStockAttribute(): int
    {
        // Get warehouse stock from ProductStock table
        $warehouseStock = \App\Models\ProductStock::where('product_id', $this->id)
            ->where('location_type', \App\Models\Warehouse::class)
            ->sum('quantity');

        return max(0, (int) $warehouseStock);
    }

    public function getRetailerStockAttribute(): int
    {
        // Get retailer stock from ProductStock table
        $retailerStock = \App\Models\ProductStock::where('product_id', $this->id)
            ->where('location_type', \App\Models\Retailer::class)
            ->sum('quantity');

        return max(0, (int) $retailerStock);
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

        // Calculate available stock from product_stocks table accounting for reservations
        // Available stock = Total stock - Reserved stock
        $stockBalances = $this->stockBalances()
            ->whereIn('location_type', ['App\\Models\\Warehouse', 'App\\Models\\Retailer'])
            ->get();

        $totalStock = $stockBalances->sum('quantity');
        $reservedStock = $stockBalances->sum('reserved_quantity');

        $availableStock = $totalStock - $reservedStock;

        // Ensure stock doesn't go negative
        return max(0, $availableStock);
    }
}
