<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Retailer;
use App\Models\Warehouse;

/**
 * Single source of truth for the per-location inventory sub-accounts.
 *
 * Inventory lives under the main Inventory account (1200) so that each
 * warehouse / retailer carries its own balance:
 *
 *     1200-WH{id}  warehouse
 *     1200-RT{id}  retailer
 *     1200-LO{id}  anything else
 *
 * Both sides of a location-to-location move have to agree on these codes, so
 * the convention lives here rather than being spelled out at each call site.
 * StockTransferObserver moves value out to a retailer; ReturnJournalHandler
 * moves it back when a retailer return is approved.
 */
final class InventoryLocationAccount
{
    /** The parent account every location sub-account hangs off. */
    public const PARENT_CODE = '1200';

    /**
     * The account code for a location, e.g. "1200-WH3".
     */
    public static function codeFor(?string $locationType, ?int $locationId): string
    {
        $prefix = match ($locationType) {
            Warehouse::class => 'WH',
            Retailer::class  => 'RT',
            default          => 'LO',
        };

        return self::PARENT_CODE . '-' . $prefix . (string) $locationId;
    }

    /**
     * The human-readable account name for a location.
     */
    public static function nameFor(?string $locationType, ?int $locationId): string
    {
        return match ($locationType) {
            Warehouse::class => "Inventory – Warehouse #{$locationId}",
            Retailer::class  => "Inventory – Retailer #{$locationId}",
            default          => "Inventory – Location #{$locationId}",
        };
    }

    /**
     * Create the sub-account if it is not there yet, and return its code.
     * Safe to call repeatedly - the account is keyed on its code.
     */
    public static function ensure(?string $locationType, ?int $locationId): string
    {
        $code = self::codeFor($locationType, $locationId);

        Account::firstOrCreate(
            ['code' => $code],
            [
                'name'            => self::nameFor($locationType, $locationId),
                'account_type_id' => AccountType::firstOrCreate(['name' => 'Asset'])->id,
                'parent_id'       => Account::where('code', self::PARENT_CODE)->value('id'),
                'opening_balance' => 0,
                'is_contra'       => false,
            ]
        );

        return $code;
    }
}
