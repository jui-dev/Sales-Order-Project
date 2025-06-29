<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;

class Grn extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'GRN';

    protected $table = 'grns';

    protected $fillable = [
        'supply_id',
        'received_date',
        'status',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
} 