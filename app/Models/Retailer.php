<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Retailer extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'RTL';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'contact_person',
        'contact_number',
        'status',
        'is_default',
        'id',
    ];

    public function stockBalances(): MorphMany
    {
        return $this->morphMany(\App\Models\ProductStock::class, 'location');
    }

    public function productStocks(): MorphMany
    {
        // Alias for stockBalances - both point to the same relationship
        return $this->stockBalances();
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

    public function stockTransactions(): MorphMany
    {
        return $this->morphMany(\App\Models\StockTransaction::class, 'location');
    }
}
