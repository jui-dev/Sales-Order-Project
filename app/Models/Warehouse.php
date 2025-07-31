<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Warehouse extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'WHS';

    protected $fillable = [
        'name',
        'address',
        'contact_person',
        'contact_number',
        'email',
        'status',
        'is_default',
        'id',
    ];

    public function stockBalances(): MorphMany
    {
        return $this->morphMany(\App\Models\ProductStock::class, 'location');
    }

    public function fulfillmentOrders(): MorphMany
    {
        return $this->morphMany(\App\Models\Order::class, 'fulfillment_location');
    }

    public function stockTransfersFrom(): MorphMany
    {
        return $this->morphMany(\App\Models\StockTransfer::class, 'from_location');
    }

    public function stockTransfersTo(): MorphMany
    {
        return $this->morphMany(\App\Models\StockTransfer::class, 'to_location');
    }
} 