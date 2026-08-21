<?php

namespace App\Services\Pricing;

use App\Models\PriceList;
use App\Models\PriceListAssignment;
use App\Models\PriceListItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only supported way to change a price.
 *
 * Every write here closes the standing row and inserts a replacement, so a
 * price change applies from the moment it is made and the previous price stays
 * on file with the dates it applied. UPDATEing unit_price in place would
 * silently rewrite what the business charged last month, which is the whole
 * problem this design exists to remove.
 */
class PriceListService
{
    /**
     * Put a price on a list, effective from $from.
     *
     * Idempotent in the useful sense: setting the price it already is does not
     * churn the history.
     */
    public function setPrice(
        PriceList $list,
        Product $product,
        float $unitPrice,
        int $minQuantity = 1,
        ?CarbonInterface $from = null,
        ?int $userId = null,
        array $derivation = [],
    ): PriceListItem {
        $from ??= Carbon::now();

        return DB::transaction(function () use ($list, $product, $unitPrice, $minQuantity, $from, $userId, $derivation) {
            $markup = $derivation['markup_percent'] ?? null;
            $basisId = $derivation['basis_price_list_item_id'] ?? null;
            $auto = (bool) ($derivation['is_auto_derived'] ?? false);
            // Told apart from "not charged": a caller that says nothing about
            // the flag is not asking to unset it, and comparing against a
            // default of false would churn a new row on every save.
            $explicitCharged = array_key_exists('is_charged', $derivation);
            $charged = (bool) ($derivation['is_charged'] ?? false);

            // The basis is part of a row's identity: one product can carry a
            // selling price per vendor cost, so setting the price derived from
            // one quote must not close the price derived from another.
            $current = $this->currentRow($list, $product, $minQuantity, $from, $basisId);

            $unchanged = $current
                && (float) $current->unit_price === round($unitPrice, 4)
                && (float) ($current->markup_percent ?? -1) === (float) ($markup ?? -1)
                && $current->basis_price_list_item_id === $basisId
                && $current->is_auto_derived === $auto
                && (! $explicitCharged || $current->is_charged === $charged);

            if ($unchanged) {
                // Nothing moved - leave the standing row alone rather than
                // closing it and opening an identical one.
                return $current;
            }

            // Close the standing row at the instant the new one starts, so the
            // two windows abut without overlapping. An overlap would make two
            // rows in force at once and the winner arbitrary.
            $current?->update(['ends_at' => $from]);

            $row = PriceListItem::create([
                'price_list_id' => $list->id,
                'product_id' => $product->id,
                'unit_price' => round($unitPrice, 4),
                'min_quantity' => $minQuantity,
                'markup_percent' => $markup,
                'basis_price_list_item_id' => $basisId,
                'is_auto_derived' => $auto,
                'is_charged' => $charged,
                'starts_at' => $from,
                'ends_at' => null,
                'created_by' => $userId,
            ]);

            if ($charged) {
                return $this->markAsCharged($row);
            }

            // A sale list must always have exactly one chargeable row per
            // product: several prices can stand at once, but an order has to
            // pay one of them. The first price set is that one by definition,
            // which also means every caller that does not care about the flag
            // - a goods receipt deriving a price, a seeder, a test - still
            // produces something orders can actually use.
            if ($list->type === PriceList::TYPE_SALE && ! $this->hasChargedRow($list, $product, $minQuantity, $row->id)) {
                return $this->markAsCharged($row);
            }

            return $row;
        });
    }

    private function hasChargedRow(PriceList $list, Product $product, int $minQuantity, int $excludingId): bool
    {
        return PriceListItem::where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->where('min_quantity', $minQuantity)
            ->where('id', '!=', $excludingId)
            ->whereNull('ends_at')
            ->where('is_charged', true)
            ->exists();
    }

    /**
     * Make one row the price orders actually charge, unmarking its siblings.
     *
     * A product may carry a selling price per vendor cost, but only one can be
     * charged - pooled stock gives a sold unit no vendor identity, so the
     * choice is recorded rather than inferred.
     */
    public function markAsCharged(PriceListItem $row): PriceListItem
    {
        return DB::transaction(function () use ($row) {
            PriceListItem::where('price_list_id', $row->price_list_id)
                ->where('product_id', $row->product_id)
                ->where('min_quantity', $row->min_quantity)
                ->where('id', '!=', $row->id)
                ->whereNull('ends_at')
                ->where('is_charged', true)
                ->update(['is_charged' => false]);

            if (! $row->is_charged) {
                $row->update(['is_charged' => true]);
            }

            return $row->refresh();
        });
    }

    /**
     * Guarantee a product's sale rows have exactly one charged among them.
     *
     * Called after a batch of edits: deleting or superseding rows can leave a
     * product with none flagged, which would make it unpriceable even though
     * prices exist. The oldest standing row is the least surprising default.
     */
    public function ensureOneCharged(PriceList $list, Product $product, int $minQuantity = 1): void
    {
        $rows = PriceListItem::where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->where('min_quantity', $minQuantity)
            ->whereNull('ends_at')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty() || $rows->where('is_charged', true)->isNotEmpty()) {
            return;
        }

        $this->markAsCharged($rows->first());
    }

    /**
     * Stop charging for a product on this list without losing what it charged.
     */
    public function removePrice(
        PriceList $list,
        Product $product,
        int $minQuantity = 1,
        ?CarbonInterface $at = null,
    ): void {
        $at ??= Carbon::now();

        // Closes every standing row for the product, not just one: a product
        // may carry a selling price per vendor cost, and "stop charging for
        // this" means all of them.
        PriceListItem::where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->where('min_quantity', $minQuantity)
            ->inForceAt($at)
            ->get()
            ->each->update(['ends_at' => $at]);
    }

    /**
     * Apply a batch of prices as one change.
     *
     * @param  array<int, array{product_id: int, unit_price: float, min_quantity?: int}>  $rows
     * @return int  how many rows actually moved
     */
    public function bulkSet(PriceList $list, array $rows, ?CarbonInterface $from = null, ?int $userId = null): int
    {
        $from ??= Carbon::now();
        $changed = 0;

        DB::transaction(function () use ($list, $rows, $from, $userId, &$changed) {
            foreach ($rows as $row) {
                $product = Product::find($row['product_id'] ?? null);

                if (! $product || ! isset($row['unit_price'])) {
                    continue;
                }

                $before = $this->currentRow($list, $product, (int) ($row['min_quantity'] ?? 1), $from);

                $item = $this->setPrice(
                    $list,
                    $product,
                    (float) $row['unit_price'],
                    (int) ($row['min_quantity'] ?? 1),
                    $from,
                    $userId,
                );

                if (! $before || $before->id !== $item->id) {
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /**
     * Attach a list to a customer, group, channel, location or vendor.
     *
     * A list with no assignments applies to everyone, so this is what narrows
     * one down.
     */
    public function assignTo(PriceList $list, Model $assignable): PriceListAssignment
    {
        return PriceListAssignment::firstOrCreate([
            'price_list_id' => $list->id,
            'assignable_type' => $assignable->getMorphClass(),
            'assignable_id' => $assignable->getKey(),
        ]);
    }

    /**
     * The purchase list carrying what one vendor charges, created on demand.
     *
     * A vendor gets a list the first time a price is agreed with them, so
     * onboarding a supplier does not require setting one up by hand. Matches
     * the code the seeding migration used, so an existing vendor's list is
     * found rather than duplicated.
     */
    public function forVendor(\App\Models\Vendor $vendor): PriceList
    {
        $existing = PriceList::ofType(PriceList::TYPE_PURCHASE)
            ->where('code', 'vendor-'.$vendor->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($vendor) {
            $list = PriceList::create([
                'name' => 'Vendor: '.$vendor->name,
                'code' => 'vendor-'.$vendor->id,
                'type' => PriceList::TYPE_PURCHASE,
                'is_active' => true,
            ]);

            $this->assignTo($list, $vendor);

            return $list;
        });
    }

    /**
     * The default list for a type, which is the fallback when nothing more
     * specific matches.
     */
    public function defaultFor(string $type): ?PriceList
    {
        return PriceList::ofType($type)->where('is_default', true)->first();
    }

    /**
     * Promote a list to be its type's default, demoting whichever held it.
     *
     * MariaDB cannot express "one default per type" as a partial unique index,
     * so the rule lives here. Going around this method can create a second
     * default, which is why nothing else should write is_default.
     */
    public function makeDefault(PriceList $list): PriceList
    {
        return DB::transaction(function () use ($list) {
            PriceList::ofType($list->type)
                ->where('id', '!=', $list->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $list->update(['is_default' => true]);

            return $list->refresh();
        });
    }

    public function create(array $attributes): PriceList
    {
        if (! in_array($attributes['type'] ?? null, [PriceList::TYPE_SALE, PriceList::TYPE_PURCHASE], true)) {
            throw new RuntimeException('A price list must be of type sale or purchase.');
        }

        return DB::transaction(function () use ($attributes) {
            $wantsDefault = (bool) ($attributes['is_default'] ?? false);
            $list = PriceList::create($attributes + ['is_default' => false]);

            return $wantsDefault ? $this->makeDefault($list) : $list;
        });
    }

    /**
     * Every price this product has ever carried, newest first.
     *
     * The payoff of effective dating, and what the price-history screen reads.
     */
    public function historyFor(Product $product, ?string $type = null)
    {
        return PriceListItem::with('priceList')
            ->where('product_id', $product->id)
            ->when($type, fn ($q) => $q->whereHas('priceList', fn ($l) => $l->where('type', $type)))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The standing row for one product on one list, at one quantity break.
     *
     * $basisId narrows it further where a product carries a selling price per
     * vendor cost. Passing null means the row derived from no basis, which is
     * its own slot rather than a wildcard - otherwise setting an unanchored
     * price would close every anchored one.
     */
    private function currentRow(
        PriceList $list,
        Product $product,
        int $minQuantity,
        CarbonInterface $at,
        ?int $basisId = null,
    ): ?PriceListItem {
        return PriceListItem::where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->where('min_quantity', $minQuantity)
            ->where('basis_price_list_item_id', $basisId)
            ->inForceAt($at)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }
}
