<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasFormattedId;

class ReturnRecord extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'RTR';

    protected $table = 'returns';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'status',
        'return_date',
        'reason',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
} 