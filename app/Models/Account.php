<?php

namespace App\Models;

use App\Accounting\AccountRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'account_type_id',
        'parent_id',
        'is_contra',
        'is_postable',
        'control_of',
        'requires_location',
        'description',
    ];

    protected $casts = [
        'is_contra'         => 'boolean',
        'is_postable'       => 'boolean',
        'requires_location' => 'boolean',
    ];

    public const CONTROL_CUSTOMER = 'customer';
    public const CONTROL_VENDOR   = 'vendor';

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // ------------------------------------------------------------------
    // Classification
    // ------------------------------------------------------------------

    /**
     * Whether a debit increases this account.
     *
     * Read off the account type, then flipped for a contra account - which is
     * the whole reason contra accounts need no special case in the reports.
     */
    public function normalBalanceIsDebit(): bool
    {
        $debitNatured = in_array($this->accountType?->name, ['Asset', 'Expense'], true);

        return $this->is_contra ? ! $debitNatured : $debitNatured;
    }

    public function isControlAccount(): bool
    {
        return $this->control_of !== null;
    }

    /** The role this account is mapped to, or null if it is outside the chart. */
    public function role(): ?AccountRole
    {
        foreach (AccountRole::cases() as $role) {
            if ($role->code() === $this->code) {
                return $role;
            }
        }

        return null;
    }
}
