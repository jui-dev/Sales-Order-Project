<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One vendor's offer of one product: whether they carry it, and at what cost.
 *
 * Read directly when maintaining a vendor's price list; reached through the
 * Vendor::products() / Product::vendors() pivot everywhere else.
 */
class VendorProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'unit_cost',
        'vendor_sku',
        'is_active',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
