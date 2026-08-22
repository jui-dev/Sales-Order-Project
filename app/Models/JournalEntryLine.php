<?php

namespace App\Models;

use App\Accounting\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One side of one journal entry, plus the dimensions it is analysed by.
 *
 * The dimensions are what stop the chart of accounts growing an account for
 * every customer, vendor and warehouse. A line against a control account
 * carries the party it belongs to, so the account reconciles to its subsidiary
 * ledger; a line against inventory carries the location, so per-location value
 * is a grouping of the one inventory account rather than a separate account
 * that nothing keeps in step with it.
 */
class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'party_type',
        'party_id',
        'location_type',
        'location_id',
        'product_id',
        'currency',
        'fx_rate',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /** The customer or vendor this line belongs to, on a control account. */
    public function party()
    {
        return $this->morphTo();
    }

    /** The warehouse or retailer this line belongs to, on inventory. */
    public function location()
    {
        return $this->morphTo();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ------------------------------------------------------------------
    // Amounts
    // ------------------------------------------------------------------

    public function debitAmount(): Money
    {
        return Money::of((string) $this->debit);
    }

    public function creditAmount(): Money
    {
        return Money::of((string) $this->credit);
    }

    /**
     * The line's effect on the account, debit positive.
     *
     * This is the convention every balance in the system is expressed in:
     * assets and expenses carry a positive balance, liabilities, equity and
     * revenue a negative one, and a contra account the opposite of its type.
     */
    public function signedAmount(): Money
    {
        return $this->debitAmount()->minus($this->creditAmount());
    }
}
