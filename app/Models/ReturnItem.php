<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class ReturnItem extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'RIT';

    public $timestamps = false;

    protected $fillable = [
        'return_id',
        'product_id',
        'quantity',
        'reason',
    ];

    public function returnRecord(): BelongsTo
    {
        return $this->belongsTo(ReturnRecord::class, 'return_id')->withDefault();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
} 