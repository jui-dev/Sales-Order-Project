<?php

namespace App\Models;

use App\Accounting\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasFormattedId;

class JournalEntry extends Model
{
    use HasFactory, HasFormattedId;

    // Without this the trait falls back to the first three letters of the class
    // name and every entry reads "JOU-0042".
    protected static string $idPrefix = 'JE';

    protected $fillable = [
        'entry_date',
        'description',
        'posted_at',
        'formatted_id',
        'source_type',
        'source_id',
        'rule_key',
        'status',
        'origin',
        'approved_at',
        'is_reverse',
        'reverses_journal_id',
        'linked_debit_note_id',
        'linked_credit_note_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at'  => 'datetime',
        'approved_at'=> 'datetime',
        'is_reverse' => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Status and origin
    // ------------------------------------------------------------------
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Where the entry came from, which decides whether it is reviewed.
     *
     * A system entry is the ledger's own record of a document that has already
     * been confirmed - approving the system's arithmetic a second time adds no
     * control, it only holds the books behind reality. A manual entry is
     * something a person typed, which is exactly where review belongs.
     */
    public const ORIGIN_SYSTEM = 'system';
    public const ORIGIN_MANUAL = 'manual';

    protected static function booted(): void
    {
        // A posted entry is a matter of record. Correcting one means posting a
        // further entry against it, never editing it in place - which is what
        // makes the audit trail worth having.
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') === self::STATUS_POSTED && $entry->isDirty()) {
                throw new \RuntimeException(sprintf(
                    'Journal entry %s is posted and cannot be changed. Reverse it with a further entry instead.',
                    $entry->formatted_id,
                ));
            }
        });

        static::deleting(function (self $entry) {
            if ($entry->status === self::STATUS_POSTED) {
                throw new \RuntimeException(sprintf(
                    'Journal entry %s is posted and cannot be deleted. Reverse it with a further entry instead.',
                    $entry->formatted_id,
                ));
            }
        });
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    /**
     * Get the original journal entry that this entry reverses
     */
    public function reversesJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_journal_id');
    }

    /**
     * Get the reverse journal entries for this journal entry
     */
    public function reverseJournals()
    {
        return $this->hasMany(JournalEntry::class, 'reverses_journal_id');
    }

    /**
     * Get the linked debit note for this journal entry
     */
    public function linkedDebitNote()
    {
        return $this->belongsTo(DebitNote::class, 'linked_debit_note_id');
    }

    /**
     * Get the linked credit note for this journal entry
     */
    public function linkedCreditNote()
    {
        return $this->belongsTo(CreditNote::class, 'linked_credit_note_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /** Only entries that are actually on the books. */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeManual($query)
    {
        return $query->where('origin', self::ORIGIN_MANUAL);
    }

    /**
     * Prefer the reference stored on the row over the one the trait derives.
     *
     * The trait's accessor shares a name with the real `formatted_id` column and
     * therefore shadows it, so a reference typed on a manual entry was written to
     * the database and then never shown again - while search and the uniqueness
     * rule went on matching the column nobody could see.
     */
    public function getFormattedIdAttribute(): string
    {
        $stored = $this->attributes['formatted_id'] ?? null;

        return $stored !== null && $stored !== '' ? $stored : $this->getCodeAttribute();
    }

    // ------------------------------------------------------------------
    // Totals
    // ------------------------------------------------------------------

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    public function debitTotal(): Money
    {
        return Money::sum($this->lines->map(fn (JournalEntryLine $l) => $l->debitAmount()));
    }

    public function creditTotal(): Money
    {
        return Money::sum($this->lines->map(fn (JournalEntryLine $l) => $l->creditAmount()));
    }

    /**
     * Balance is decided on integer minor units.
     *
     * This used to be `round($debit, 2) === round($credit, 2)`, which compares
     * two floats for exact identity - an entry could fail to balance by a value
     * no report was able to display.
     */
    public function isBalanced(): bool
    {
        return $this->debitTotal()->equals($this->creditTotal());
    }

    // ------------------------------------------------------------------
    // Manual review path
    // ------------------------------------------------------------------

    public function isSystemGenerated(): bool
    {
        return $this->origin === self::ORIGIN_SYSTEM;
    }

    /**
     * Posting is the single point at which a manual entry reaches the ledger.
     *
     * System entries never travel this path: they are written posted, because
     * the document behind them was already confirmed by the person who
     * confirmed the document.
     */
    public function post(): void
    {
        if ($this->status !== self::STATUS_APPROVED) {
            throw new \RuntimeException('Only approved entries can be posted. Approve the entry first.');
        }

        if (! $this->isBalanced()) {
            throw new \RuntimeException('Cannot post an unbalanced journal entry.');
        }

        $this->update([
            'status'    => self::STATUS_POSTED,
            'posted_at' => now(),
        ]);
    }

    public function approve(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft entries can be approved.');
        }

        $this->update([
            'status'      => self::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        if ($this->status === self::STATUS_POSTED) {
            throw new \RuntimeException('A posted entry cannot be rejected; correct it with a further entry.');
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
        ]);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBePosted(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
