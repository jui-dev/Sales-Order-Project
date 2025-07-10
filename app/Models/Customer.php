<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;

class Customer extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'CUS';

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'address',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }
} 