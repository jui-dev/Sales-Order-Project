<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'pricing_mode',
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

    /**
     * The vendors who can supply this product, carrying each one's cost.
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Vendor::class, 'vendor_products')
            ->withPivot('unit_cost', 'vendor_sku', 'is_active')
            ->withTimestamps();
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

    /**
     * Every price this product carries on any list, past and present.
     * Read through PriceResolver rather than directly when pricing anything.
     */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(\App\Models\PriceListItem::class);
    }

    /**
     * The costing ledger - what the stock on hand has been worth over time.
     * See [ProductCostService].
     */
    public function costs(): HasMany
    {
        return $this->hasMany(\App\Models\ProductCost::class);
    }

    /* ---------------------------------------------------------------------
     | Pricing
     |---------------------------------------------------------------------*/

    /**
     * Attach the product's current sale price and cost as selectable columns.
     *
     * Prices live in dated rows on price lists rather than in a column here, so
     * a listing that wants to show, filter or sort by price has to reach them
     * in SQL - fetching per row would be a query per product.
     *
     * The price is LEFT JOINed rather than sub-selected because it is filtered
     * and sorted on. Only one row per (list, product, min_quantity) can be in
     * force at a time, so the join cannot multiply rows. Cost is a plain
     * sub-select: it is displayed but never filtered.
     *
     * Exposes `current_price` and `current_cost`.
     *
     * Because it joins, any further constraint must qualify its columns -
     * `where('products.id', ...)`, not `where('id', ...)`, which the database
     * will reject as ambiguous.
     */
    public function scopeWithCurrentPricing(
        \Illuminate\Database\Eloquent\Builder $query,
        ?\DateTimeInterface $at = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $at ??= now();

        $defaultSaleList = \App\Models\PriceList::query()
            ->where('type', \App\Models\PriceList::TYPE_SALE)
            ->where('is_default', true)
            ->value('id');

        return $query
            ->select('products.*')
            ->leftJoin('price_list_items as current_price_row', function ($join) use ($at, $defaultSaleList) {
                $join->on('current_price_row.product_id', '=', 'products.id')
                    ->where('current_price_row.price_list_id', '=', $defaultSaleList)
                    ->where('current_price_row.min_quantity', '=', 1)
                    ->where('current_price_row.starts_at', '<=', $at)
                    ->where(function ($q) use ($at) {
                        $q->whereNull('current_price_row.ends_at')
                            ->orWhere('current_price_row.ends_at', '>', $at);
                    });
            })
            ->addSelect(['current_price_row.unit_price as current_price'])
            ->addSelect([
                'current_cost' => \App\Models\ProductCost::query()
                    ->select('unit_cost')
                    ->whereColumn('product_costs.product_id', 'products.id')
                    ->where('product_costs.effective_at', '<=', $at)
                    ->orderByDesc('product_costs.effective_at')
                    ->orderByDesc('product_costs.id')
                    ->limit(1),
            ]);
    }

    /**
     * This product's current sale price on the default list, or null.
     *
     * For a single product. A listing should use scopeWithCurrentPricing so it
     * does not run a query per row.
     */
    public function currentPrice(): ?float
    {
        $resolved = app(\App\Services\Pricing\PriceResolver::class)->forSale($this);

        return $resolved?->unitPrice;
    }

    /** What the stock on hand is currently carried at, or null if unknown. */
    public function currentCost(): ?float
    {
        return app(\App\Services\Pricing\ProductCostService::class)->costAt($this);
    }

    /* ---------------------------------------------------------------------
     | Computed Attributes
     |---------------------------------------------------------------------*/

    /*
     * There was a "gp" accessor here, gated on the product having a confirmed
     * order but returning gross_profit - the catalogue margin, which does not
     * depend on any order. The gate implied a realised figure and the value was
     * a list one, so a returned sale left it unchanged and it read as though the
     * product were still earning.
     *
     * Nothing referenced it outside its own test. Realised profit now comes off
     * the ledger, where returns already are: ReportService::realisedProfitByProduct().
     * The catalogue margin is still gross_profit, owned by ProductObserver.
     */

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
