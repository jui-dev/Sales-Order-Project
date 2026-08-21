<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Customer extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'CUS';

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'address',
        'customer_group_id',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }

    /**
     * Retail, wholesale, distributor - whichever set of prices this customer
     * buys at. Named group() rather than customerGroup() because PriceContext
     * reads it on every resolution.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CustomerGroup::class, 'customer_group_id');
    }

    /** A rate negotiated with this customer alone. */
    public function priceListAssignments(): MorphMany
    {
        return $this->morphMany(\App\Models\PriceListAssignment::class, 'assignable');
    }
} 