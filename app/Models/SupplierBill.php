<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Traits\HasFormattedId;
use Carbon\Carbon;

class SupplierBill extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'SB';

    protected $fillable = [
        'formatted_id',
        'grn_id',
        'vendor_id',
        'bill_date',
        'description',
        'total_amount',
        'status',
        'purchase_journal_id',
        'payment_journal_id',
        'posted_at',
        'paid_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'posted_at' => 'datetime',
        'paid_at'   => 'datetime',
    ];

    /* ---------------------------------------------------------------------
     | Relationships
     |---------------------------------------------------------------------*/
    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    public function purchaseJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'purchase_journal_id');
    }

    public function paymentJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(SupplierBillPayment::class);
    }

    /* ---------------------------------------------------------------------
     | Status Helpers
     |---------------------------------------------------------------------*/
    public function post(): void
    {
        $this->status    = 'posted';
        $this->posted_at = Carbon::now();
        $this->save();
    }

    public function isPaid(): bool
    {
        return $this->payment && $this->payment->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return !$this->payment || $this->payment->payment_status === 'unpaid';
    }
} 