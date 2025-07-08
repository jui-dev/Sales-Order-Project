<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Traits\HasFormattedId;
use App\Models\Invoice;

class Order extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'ORD';

    protected $fillable = [
        'customer_id',
        'status',
        'order_date',
        'total_amount',
        'notes',
        'fulfillment_location_id',
        'fulfillment_location_type',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->items();
    }

    public function fulfillmentLocation(): MorphTo
    {
        return $this->morphTo('fulfillment_location');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
} 