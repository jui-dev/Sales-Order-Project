<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasFormattedId;

class JournalEntry extends Model
{
    use HasFactory, HasFormattedId;

    protected $fillable = [
        'entry_date',
        'description',
        'posted_at',
        'formatted_id',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }
} 