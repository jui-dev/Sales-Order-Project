<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Vendor extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'VND';

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
    ];

    public function supplies(): HasMany
    {
        return $this->hasMany(\App\Models\Supply::class);
    }

    /**
     * The products this vendor can supply, carrying their agreed cost.
     *
     * unit_cost is nullable on the pivot - assignment and pricing are separate
     * decisions, so an unpriced row means "they carry it, we have not agreed a
     * price yet" rather than "it is free".
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vendor_products')
            ->withPivot('unit_cost', 'vendor_sku', 'is_active')
            ->withTimestamps();
    }

    /**
     * The price-list rows themselves, for screens that edit them.
     */
    public function vendorProducts(): HasMany
    {
        return $this->hasMany(VendorProduct::class);
    }

    /**
     * The purchase price list carrying what this vendor charges over time.
     *
     * The pivot above says which products they carry; this says what each has
     * cost and when, so a quote agreed today cannot restate a PO raised last
     * month. Resolve through PriceResolver::forPurchase().
     */
    public function priceListAssignments(): MorphMany
    {
        return $this->morphMany(PriceListAssignment::class, 'assignable');
    }

    public function purchasePriceList(): ?PriceList
    {
        return PriceList::ofType(PriceList::TYPE_PURCHASE)
            ->whereHas('assignments', fn ($q) => $q
                ->where('assignable_type', $this->getMorphClass())
                ->where('assignable_id', $this->getKey()))
            ->first();
    }
} 