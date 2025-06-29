<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class StockTransferItem extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'XIT';

    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id')->withDefault();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
} 