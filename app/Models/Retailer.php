<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Traits\HasFormattedId;

class Retailer extends Model
{
    use HasFormattedId;

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
} 