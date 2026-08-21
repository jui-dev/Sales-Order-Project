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
    ): PriceListItem {
        $from ??= Carbon::now();

        return DB::transaction(function () use ($list, $product, $unitPrice, $minQuantity, $from, $userId) {
            $current = $this->currentRow($list, $product, $minQuantity, $from);

            if ($current && (float) $current->unit_price === round($unitPrice, 4)) {
                // No change - leave the standing row alone rather than closing
                // it and opening an identical one.
                return $current;
            }

            // Close the standing row at the instant the new one starts, so the
            // two windows abut without overlapping. An overlap would make two
            // rows in force at once and the winner arbitrary.
            $current?->update(['ends_at' => $from]);

            return PriceListItem::create([
                'price_list_id' => $list->id,
                'product_id' => $product->id,
                'unit_price' => round($unitPrice, 4),
                'min_quantity' => $minQuantity,
                'starts_at' => $from,
                'ends_at' => null,
                'created_by' => $userId,
            ]);
        });
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

        $this->currentRow($list, $product, $minQuantity, $at)?->update(['ends_at' => $at]);
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

    private function currentRow(
        PriceList $list,
        Product $product,
        int $minQuantity,
        CarbonInterface $at,
    ): ?PriceListItem {
        return PriceListItem::where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->where('min_quantity', $minQuantity)
            ->inForceAt($at)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }
}
