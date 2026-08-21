<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductCost;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What the goods on hand are worth, and what they were worth on any past date.
 *
 * The old behaviour took the newest delivery's unit cost and wrote it over
 * products.purchase_price. With 50 units on hand at 400, receiving 5 more at
 * 200 restated all 55 at 200 - below what most of them actually cost - and,
 * because everything downstream read that column live, retroactively re-valued
 * every sale, return and report that had already happened.
 *
 * A weighted moving average fixes the arithmetic; appending rather than
 * overwriting fixes the history.
 */
class ProductCostService
{
    /**
     * Record a receipt and return the new average cost.
     *
     * The average is struck over the stock already on hand *before* this
     * delivery, which is why quantity_on_hand is carried on the row: it saves
     * replaying the whole ledger, and it is the figure the average was actually
     * weighted against.
     */
    public function recordReceipt(
        Product $product,
        int $quantity,
        float $unitCost,
        ?CarbonInterface $at = null,
        ?Model $source = null,
    ): ProductCost {
        $at ??= Carbon::now();

        $previous = $this->latestFor($product, $at);

        // Stock already held, valued at the standing average.
        $priorQuantity = max(0, (int) ($previous->quantity_on_hand ?? 0));
        $priorCost = (float) ($previous->unit_cost ?? 0);

        $incomingQuantity = max(0, $quantity);
        $totalQuantity = $priorQuantity + $incomingQuantity;

        if ($totalQuantity <= 0) {
            // Nothing on hand either side of the receipt - there is no quantity
            // to weight by, so the delivery's own cost is the only figure there
            // is. Recorded rather than skipped: it is still what we paid.
            $newCost = $unitCost;
        } else {
            $newCost = (($priorQuantity * $priorCost) + ($incomingQuantity * $unitCost)) / $totalQuantity;
        }

        return ProductCost::create([
            'product_id' => $product->id,
            'unit_cost' => round($newCost, 4),
            'quantity_on_hand' => $totalQuantity,
            'effective_at' => $at,
            'source_type' => $source ? $source->getMorphClass() : null,
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * The cost in force at $at - defaults to now.
     *
     * Returns null when the product had no recorded cost at that point, which
     * a caller must tell apart from a cost of zero: "we do not know what this
     * cost" is not "this was free".
     */
    public function costAt(Product $product, ?CarbonInterface $at = null): ?float
    {
        $row = $this->latestFor($product, $at ?? Carbon::now());

        return $row ? (float) $row->unit_cost : null;
    }

    /**
     * Cost at $at, falling back to the product's legacy column.
     *
     * A bridge for callers that have to produce a number no matter what while
     * the ledger is still being backfilled. Prefer costAt() and handle the null
     * where the caller can say something more useful than a guess.
     */
    public function costAtOrLegacy(Product $product, ?CarbonInterface $at = null): float
    {
        return $this->costAt($product, $at) ?? (float) ($product->purchase_price ?? 0);
    }

    /** The whole cost history, newest first - what the audit screen shows. */
    public function historyFor(Product $product)
    {
        return ProductCost::where('product_id', $product->id)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get();
    }

    private function latestFor(Product $product, CarbonInterface $at): ?ProductCost
    {
        return ProductCost::where('product_id', $product->id)
            ->inForceAt($at)
            ->first();
    }
}
