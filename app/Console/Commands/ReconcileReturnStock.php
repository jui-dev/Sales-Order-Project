<?php

namespace App\Console\Commands;

use App\Models\ProductStock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class ReconcileReturnStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'returns:reconcile-stock
                            {--product-id= : Limit the report to a single product ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report drift between product_stocks balances and the stock movements returns should have made (read-only)';

    /**
     * Read-only by design. Returns used to post their stock effect more than
     * once, so live balances may have drifted; this reports the gap so it can
     * be reviewed before anybody decides to correct it.
     */
    public function handle(): int
    {
        $this->info('Reconciling return stock movements against product_stocks...');
        $this->newLine();

        $productId = $this->option('product-id');

        // Expected net movement per location, keyed product|locationType|locationId.
        $expected = [];

        $returns = StockTransaction::query()
            ->whereIn('transaction_type', StockTransaction::RETURN_TYPES)
            ->whereIn('status', [StockTransaction::STATUS_APPROVED, StockTransaction::STATUS_COMPLETED])
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->get();

        foreach ($returns as $return) {
            foreach ($this->expectedMovements($return) as [$locationType, $locationId, $delta]) {
                $key = "{$return->product_id}|{$locationType}|{$locationId}";

                if (!isset($expected[$key])) {
                    $expected[$key] = [
                        'product_id' => $return->product_id,
                        'location_type' => $locationType,
                        'location_id' => $locationId,
                        'delta' => 0,
                    ];
                }

                $expected[$key]['delta'] += $delta;
            }
        }

        if (empty($expected)) {
            $this->info('No approved or completed returns found. Nothing to reconcile.');
            return self::SUCCESS;
        }

        // Non-return movements are the baseline every location started from.
        $rows = [];
        $driftCount = 0;

        foreach ($expected as $entry) {
            $current = ProductStock::where('product_id', $entry['product_id'])
                ->where('location_type', $entry['location_type'])
                ->where('location_id', $entry['location_id'])
                ->value('quantity');

            $current = (int) ($current ?? 0);

            $nonReturnBaseline = $this->nonReturnBaseline(
                $entry['product_id'],
                $entry['location_type'],
                $entry['location_id']
            );

            $shouldBe = $nonReturnBaseline + $entry['delta'];
            $drift = $current - $shouldBe;

            if ($drift === 0) {
                continue;
            }

            $driftCount++;

            $rows[] = [
                $entry['product_id'],
                class_basename($entry['location_type']) . ' #' . $entry['location_id'],
                $current,
                $shouldBe,
                sprintf('%+d', $drift),
            ];
        }

        if ($driftCount === 0) {
            $this->info('No drift detected. Every location matches its expected balance.');
            return self::SUCCESS;
        }

        $this->warn("Drift detected in {$driftCount} product/location balance(s):");
        $this->newLine();

        $this->table(
            ['Product', 'Location', 'Current', 'Expected', 'Drift'],
            $rows
        );

        $this->newLine();
        $this->warn('This command made no changes. Review the drift above before correcting anything.');
        $this->line('Expected = movements from non-return transactions + one application of each approved/completed return.');

        return self::SUCCESS;
    }

    /**
     * The stock movements a single return should have caused, exactly once.
     *
     * @return array<int, array{0: string, 1: int, 2: int}> [locationType, locationId, delta]
     */
    private function expectedMovements(StockTransaction $return): array
    {
        switch ($return->transaction_type) {
            case StockTransaction::TYPE_VENDOR_RETURN:
                // Goods leave our warehouse and go back to the supplier.
                if ($return->location_type !== Warehouse::class) {
                    return [];
                }

                return [[Warehouse::class, (int) $return->location_id, -$return->quantity]];

            case StockTransaction::TYPE_RETAILER_RETURN:
                // Goods leave the retailer and come back to the source warehouse.
                $transfer = $return->stockTransfer;

                if (!$transfer || !$this->isWarehouse($transfer->from_location_type)) {
                    return [];
                }

                return [
                    [$return->location_type, (int) $return->location_id, -$return->quantity],
                    [Warehouse::class, (int) $transfer->from_location_id, $return->quantity],
                ];

            case StockTransaction::TYPE_CUSTOMER_RETURN:
                // Goods come back into whichever internal location took them.
                if (!in_array($return->location_type, [Warehouse::class, \App\Models\Retailer::class], true)) {
                    return [];
                }

                $delta = in_array($return->direction, ['out', 'outbound'], true)
                    ? -$return->quantity
                    : $return->quantity;

                return [[$return->location_type, (int) $return->location_id, $delta]];
        }

        return [];
    }

    /**
     * Net movement at a location from everything that is not a return.
     */
    private function nonReturnBaseline(int $productId, string $locationType, int $locationId): int
    {
        $transactions = StockTransaction::query()
            ->where('product_id', $productId)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->whereNotIn('transaction_type', StockTransaction::RETURN_TYPES)
            ->get(['quantity', 'direction']);

        $total = 0;

        foreach ($transactions as $transaction) {
            $total += in_array($transaction->direction, ['out', 'outbound'], true)
                ? -$transaction->quantity
                : $transaction->quantity;
        }

        return $total;
    }

    private function isWarehouse(?string $locationType): bool
    {
        return in_array($locationType, ['App\\Models\\Warehouse', Warehouse::class], true);
    }
}
