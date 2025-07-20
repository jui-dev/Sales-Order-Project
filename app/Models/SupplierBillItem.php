<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillItem extends Model
{
    protected $fillable = [
        'supplier_bill_id',
        'product_id',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    /* Relationships */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
} 