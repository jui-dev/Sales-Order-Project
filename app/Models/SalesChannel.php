<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The route a sale came in through - counter, phone, marketplace.
 *
 * The same product can be worth charging differently depending on how it was
 * sold, so a channel is something a price list can be assigned to.
 */
class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function priceListAssignments(): MorphMany
    {
        return $this->morphMany(PriceListAssignment::class, 'assignable');
    }
}
