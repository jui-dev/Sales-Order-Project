<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PickingList;
use App\Models\ProductStock;
use App\Models\StockTransaction;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class PickingListObserver
{
    /**
     * Handle the PickingList "updated" event.
     * When the status transitions to completed/closed we deduct the physical stock
     * and release the reservation.
     */
    public function updated(PickingList $list): void
    {
        // detect status change to a completed state
        $completedStates = ['completed', 'closed', 'verified'];
        if ($list->wasChanged('status') && in_array($list->status, $completedStates, true)) {
            $this->finaliseStockMovements($list);
        }
    }

    /**
     * The cost each product was captured at on the order this list fulfils.
     *
     * Keyed by product_id. Empty for lists that are not fulfilling an order -
     * a stock transfer has no sale behind it to take a cost basis from.
     *
     * @return array<int, float>
     */
    private function orderLineCosts(PickingList $list): array
    {
        if ($list->reference_type !== Order::class) {
            return [];
        }

        return \App\Models\OrderItem::where('order_id', $list->reference_id)
            ->whereNotNull('unit_cost')
            ->pluck('unit_cost', 'product_id')
            ->map(fn ($cost) => (float) $cost)
            ->all();
    }

    /**
     * Cost basis for one picked line.
     *
     * Prefers the cost the order captured, so that posting a goods receipt
     * cannot change the COGS of an order placed before it. Falls back to the
     * product's current cost only where nothing was captured - transfers, and
     * lines predating the unit_cost column.
     *
     * @param  array<int, float>  $orderCosts
     */
    private function unitCostFor($item, array $orderCosts): float
    {
        return $orderCosts[$item->product_id]
            ?? (float) ($item->product->purchase_price ?? 0);
    }

    private function finaliseStockMovements(PickingList $list): void
    {
        DB::transaction(function () use ($list) {
            // Cost of what actually leaves, accumulated as the lines are walked and
            // posted to the ledger once below.
            $list->loadMissing('items.product');

            // When the list is fulfilling an order, the cost basis is the one that
            // order captured when it was placed - not whatever the product costs
            // today. Loaded once here rather than per line.
            $orderCosts = $this->orderLineCosts($list);
            $costOfGoods = 0;

            foreach ($list->items as $item) {
                // Determine source location (from_location)
                $locationType = $list->from_location_type ?? $list->fromLocation?->getMorphClass();
                $locationId   = $list->from_location_id;

                /** @var ProductStock|null $stock */
                $stock = ProductStock::where('product_id', $item->product_id)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    // Create stock row if somehow missing (should not happen)
                    $stock = ProductStock::create([
                        'product_id'       => $item->product_id,
                        'location_type'    => $locationType,
                        'location_id'      => $locationId,
                        'quantity'         => 0,
                        'reserved_quantity'=> 0,
                    ]);
                }

                // What was held versus what actually goes. A short pick still
                // releases the whole reservation - the rest of the line is not
                // going anywhere once the list is closed - but only the picked
                // quantity is allowed to move.
                $reservedQty = $item->quantity_requested;
                $qty         = (int) $item->quantity_picked;

                // Release reservation first (cannot go below 0)
                $reservedAfter = max(0, $stock->reserved_quantity - $reservedQty);
                $stock->reserved_quantity = $reservedAfter;

                // Deduct physical stock
                $stock->quantity = $stock->quantity - $qty;
                $stock->save();

                // Nothing was picked on this line, so there is no movement to
                // record on either side.
                if ($qty <= 0) {
                    continue;
                }

                // Value what is going at the picked quantity, never the requested
                // one - a short pick must not charge cost that stayed on the shelf.
                $costOfGoods += $qty * $this->unitCostFor($item, $orderCosts);

                // Determine correct transaction type (transfer vs order)
                $txnType = $list->reference_type === \App\Models\StockTransfer::class
                    ? StockTransaction::TYPE_STOCK_TRANSFER
                    : StockTransaction::TYPE_ORDER_FULFILLMENT;

                // Record outbound transaction (source warehouse)
                // Note: We manually update stock above, so we need to prevent
                // StockTransactionObserver from double-deducting. We'll disable
                // the observer for this transaction by temporarily storing a flag.
                $skipStockUpdate = true;

                $transaction = StockTransaction::withoutEvents(function() use ($item, $stock, $qty, $txnType, $list) {
                    return StockTransaction::create([
                        'product_id'       => $item->product_id,
                        'location_id'      => $stock->location_id,
                        'location_type'    => $stock->location_type,
                        'quantity'         => $qty,
                        'direction'        => 'outbound',
                        'transaction_type' => $txnType,
                        'reference_type'   => PickingList::class,
                        'reference_id'     => $list->id,
                        'transaction_date' => now(),
                        'status'           => 'completed', // Stock movement is finalized
                    ]);
                });

                /* -------------------------------------------------------
                 |  Inbound side (destination) – for transfers only
                 -------------------------------------------------------*/
                if ($list->to_location_id && in_array($list->to_location_type, [\App\Models\Warehouse::class, \App\Models\Retailer::class], true)) {
                    // Increase stock at destination
                    $destStock = ProductStock::firstOrNew([
                        'product_id'    => $item->product_id,
                        'location_id'   => $list->to_location_id,
                        'location_type' => $list->to_location_type,
                    ], ['quantity' => 0, 'reserved_quantity' => 0]);

                    $destStock->quantity += $qty;
                    $destStock->save();

                    // Inbound transaction record
                    // Note: We manually update stock above, so prevent observer from double-updating
                    $inboundTransaction = StockTransaction::withoutEvents(function() use ($item, $destStock, $qty, $txnType, $list) {
                        return StockTransaction::create([
                            'product_id'       => $item->product_id,
                            'location_id'      => $destStock->location_id,
                            'location_type'    => $destStock->location_type,
                            'quantity'         => $qty,
                            'direction'        => 'inbound',
                            'transaction_type' => $txnType,
                            'reference_type'   => PickingList::class,
                            'reference_id'     => $list->id,
                            'transaction_date' => now(),
                            'status'           => 'completed', // Stock movement is finalized
                        ]);
                    });
                }
            }

            // Goods have left on a sale, so the ledger has to stop carrying them as
            // inventory and start carrying them as cost. Only sales do this:
            // a transfer keeps the value inside the business, and
            // StockTransferObserver already moves it between location sub-accounts.
            if ($list->reference_type === Order::class) {
                $this->postCostOfGoodsSold($list, $costOfGoods);
            }

            // Mark completed timestamp if absent
            if (empty($list->completed_at)) {
                $list->updateQuietly(['completed_at' => now()]);
            }

            // If the picking list is associated with a sales order, mark that order as completed so
            // profitability figures (GP) can be surfaced only after fulfilment is done.
            if ($list->reference_type === \App\Models\Order::class) {
                /** @var \App\Models\Order|null $order */
                $order = \App\Models\Order::find($list->reference_id);
                if ($order && $order->status !== 'completed') {
                    $order->updateQuietly(['status' => 'completed']);

                    // Auto-generate invoice upon completion
                    try {
                        $invoice = app(\App\Services\InvoiceService::class)->generateFromOrder($order);
                        \Log::info('Invoice generated successfully for Order '.$order->id.': Invoice #'.$invoice->invoice_number);
                    } catch (\Throwable $e) {
                        \Log::error('Failed to auto-generate invoice for Order '.$order->id.' after picking completion: '.$e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * Relieve inventory and charge the cost of the goods that just shipped.
     *
     *     Dr 5000 Cost of Goods Sold
     *     Cr 1200 Inventory
     *
     * The credit goes to the parent Inventory account because that is where
     * supplier bills debit it (see SupplierBillService::postSupplierBill); the
     * 1200-WH* / 1200-RT* sub-accounts are only used by transfers.
     */
    private function postCostOfGoodsSold(PickingList $list, float $costOfGoods): void
    {
        $costOfGoods = round($costOfGoods, 2);

        // Nothing picked, or products carrying no purchase price. AccountingService
        // rejects a zero-total entry, so there is nothing to post either way.
        if ($costOfGoods <= 0) {
            return;
        }

        // A picking list can be saved again after completion; the cost must only
        // be charged once.
        $exists = JournalEntry::where('source_type', $list->getMorphClass())
            ->where('source_id', $list->getKey())
            ->exists();

        if ($exists) {
            return;
        }

        $reference = $list->picking_number ?: ('PL-' . $list->id);
        $description = 'Cost of goods sold – Picking ' . $reference;

        try {
            /** @var AccountingService $acct */
            $acct = App::make(AccountingService::class);
            $acct->post([
                [
                    'account_code' => '5000', // Cost of Goods Sold
                    'debit'        => $costOfGoods,
                    'credit'       => 0,
                    'description'  => $description,
                ],
                [
                    'account_code' => '1200', // Inventory
                    'debit'        => 0,
                    'credit'       => $costOfGoods,
                    'description'  => 'Inventory relieved – Picking ' . $reference,
                ],
            ], $list->completed_at ?? now(), $description, $list);
        } catch (\Throwable $e) {
            // The stock movement is the record of truth and must not be rolled
            // back because the ledger could not take the entry - a missing
            // account is a chart-of-accounts problem, not a picking problem.
            \Log::error('Failed to post COGS for Picking List '.$list->id.': '.$e->getMessage());
        }
    }
}
