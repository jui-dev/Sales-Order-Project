<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;
use App\Models\Invoice;
use App\Models\PickingList;

class Order extends Model
{
    use HasFactory, HasFormattedId;

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
    
    // Add an alias for backward compatibility
    public function getFulfillmentLocationAttribute()
    {
        return $this->fulfillmentLocation()->withDefault()->getResults();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The picking list raised when the order is confirmed.
     *
     * PickingList points back through a reference_type/reference_id pair rather
     * than an order_id, so the type is constrained here. Newest first: a manual
     * completion can raise a second list (see OrderService::deductOrderStock).
     */
    public function pickingList(): HasOne
    {
        return $this->hasOne(PickingList::class, 'reference_id')
            ->where('reference_type', self::class)
            ->latest('id');
    }
} 