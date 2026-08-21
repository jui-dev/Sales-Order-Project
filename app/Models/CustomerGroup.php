<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A kind of customer that gets its own prices - retail, wholesale, distributor.
 *
 * This is what makes "wholesale price" a thing without a wholesale_price column
 * on every product: a price list assigned to the group, and the customers who
 * belong to it resolve against it.
 */
class CustomerGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function priceListAssignments(): MorphMany
    {
        return $this->morphMany(PriceListAssignment::class, 'assignable');
    }
}
