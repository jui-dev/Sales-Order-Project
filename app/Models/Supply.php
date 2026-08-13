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
} 