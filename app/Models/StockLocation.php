<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasFormattedId;

class StockLocation extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'LOC';

    protected $table = 'stock_locations';

    protected $fillable = [
        'name',
        'type',
        'address',
        'contact_person',
        'contact_number',
        'email',
        'status',
        'is_default',
    ];

    public function stockBalances()
    {
        return $this->hasMany(ProductStock::class, 'location_id')
                    ->where('location_type', self::class);
    }

    public function stockTransactions()
    {
        return $this->hasMany(StockTransaction::class, 'location_id')
                    ->where('location_type', self::class);
    }

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'location_id')
                    ->where('location_type', self::class);
    }
} 