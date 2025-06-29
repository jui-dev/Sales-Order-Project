<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Traits\HasFormattedId;

class Warehouse extends Model
{
    use HasFormattedId;

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
} 