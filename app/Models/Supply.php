<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Supply extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'SUP';

    protected $fillable = [
        'purchase_order_id',
        'vendor_id',
        'warehouse_id',
        'status',
        'supply_date',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'supply_date' => 'date',
    ];

    /**
     * The order this delivery was made against, when there was one.
     *
     * Null for supplies recorded before purchase orders existed, and for
     * deliveries that arrived without an order behind them.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withDefault();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyItem::class);
    }

    public function grn(): HasOne
    {
        return $this->hasOne(\App\Models\Grn::class);
    }

    /**
     * Stock transactions raised for this supply. Older rows store the reference
     * type as the plain string 'supply', newer ones as the model class.
     */
    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'reference_id')
            ->whereIn('reference_type', ['supply', self::class]);
    }
}
