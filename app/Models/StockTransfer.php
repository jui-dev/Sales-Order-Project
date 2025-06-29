<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasFormattedId;

class StockTransfer extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'XFR';

    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'from_location_type',
        'to_location_type',
        'status',
        'transfer_date',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
} 