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
        // The relation name has to match the method, or eager loading cannot
        // resolve it: with('fromLocation') went looking for a relation called
        // from_location and threw. PickingList already does this correctly.
        return $this->morphTo(__FUNCTION__, 'from_location_type', 'from_location_id');
    }

    public function toLocation(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'to_location_type', 'to_location_id');
    }
} 