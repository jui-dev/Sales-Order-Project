<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A window of time the books are kept in.
 *
 * Without this there was nothing to stop a posting landing in a month that had
 * already been reported on, and no moment at which profit could be moved into
 * retained earnings - which is why the balance sheet had to derive the figure
 * on every render just to make the accounting equation hold.
 */
class FiscalPeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'code',
        'starts_on',
        'ends_on',
        'status',
        'closing_entry_id',
        'closed_at',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
        'closed_at' => 'datetime',
    ];

    public function closingEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }

    // ------------------------------------------------------------------
    // Lookup
    // ------------------------------------------------------------------

    public static function containing(CarbonInterface $date): ?self
    {
        return static::whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    /**
     * The period covering a date, created as an open month if absent.
     *
     * Periods are calendar months. Creating them on demand means the books
     * work out of the box; closing one is still an explicit act.
     */
    public static function findOrCreateFor(CarbonInterface $date): self
    {
        if ($period = static::containing($date)) {
            return $period;
        }

        $start = Carbon::parse($date)->startOfMonth();

        return static::create([
            'code'      => $start->format('Y-m'),
            'starts_on' => $start->toDateString(),
            'ends_on'   => $start->copy()->endOfMonth()->toDateString(),
            'status'    => self::STATUS_OPEN,
        ]);
    }

    // ------------------------------------------------------------------
    // State
    // ------------------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function label(): string
    {
        return $this->starts_on?->format('F Y') ?? $this->code;
    }
}
