<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
} 