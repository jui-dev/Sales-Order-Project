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
} 