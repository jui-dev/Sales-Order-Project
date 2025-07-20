<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasFormattedId;
use Carbon\Carbon;

class SupplierBillPayment extends Model
{
    use HasFormattedId;

    protected static string $idPrefix = 'SBP';

    protected $fillable = [
        'formatted_id',
        'supplier_bill_id',
        'vendor_id',
        'payment_amount',
        'payment_status',
        'payment_journal_id',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    /* ---------------------------------------------------------------------
     | Relationships
     |---------------------------------------------------------------------*/
    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_id');
    }

    /* ---------------------------------------------------------------------
     | Status Helpers
     |---------------------------------------------------------------------*/
    public function markAsPaid(): void
    {
        $this->payment_status = 'paid';
        $this->paid_at = Carbon::now();
        $this->save();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }
}
