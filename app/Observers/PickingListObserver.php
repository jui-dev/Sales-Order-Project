<?php

namespace App\Observers;

use App\Models\PickingList;
use App\Models\ProductStock;
use App\Models\StockTransaction;
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

    private function finaliseStockMovements(PickingList $list): void
    {
        DB::transaction(function () use ($list) {
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

                $qty = $item->quantity_requested;

                // Release reservation first (cannot go below 0)
                $reservedAfter = max(0, $stock->reserved_quantity - $qty);
                $stock->reserved_quantity = $reservedAfter;

                // Deduct physical stock
                $stock->quantity = $stock->quantity - $qty;
                $stock->save();

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
}
