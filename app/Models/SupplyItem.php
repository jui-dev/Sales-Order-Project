<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class SupplyItem extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'SIT';

    public $timestamps = false;

    protected $fillable = [
        'supply_id',
        'product_id',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class)->withDefault();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
} 