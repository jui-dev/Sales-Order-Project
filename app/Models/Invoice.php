<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasFormattedId;
use App\Models\Payment;

class Invoice extends Model
{
    use HasFactory, HasFormattedId;

    protected static string $idPrefix = 'INV';

    protected $fillable = [
        'invoice_number',
        'order_id',
        'customer_id',
        'invoice_date',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the sales journal entry for this invoice
     */
    public function salesJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'id', 'source_id')
            ->where('source_type', Invoice::class);
    }
} 