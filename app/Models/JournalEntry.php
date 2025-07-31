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
        // Added for status workflow
        'status',
        'approved_at',
        // Added for reverse journal functionality
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

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    // ------------------------------------------------------------------
    // Status constants
    // ------------------------------------------------------------------
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function isBalanced(): bool
    {
        return round($this->totalDebit(), 2) === round($this->totalCredit(), 2);
    }

    public function post(): void
    {
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
        $this->update([
            'status'      => self::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
        ]);
    }
} 