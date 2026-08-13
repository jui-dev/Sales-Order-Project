<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class StockTransfer extends Model
{
    use HasFactory, HasFormattedId;

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

    public function fromLocation(): MorphTo
    {
        return $this->morphTo('from_location', 'from_location_type', 'from_location_id');
    }

    public function toLocation(): MorphTo
    {
        return $this->morphTo('to_location', 'to_location_type', 'to_location_id');
    }
} 