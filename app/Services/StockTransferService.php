<?php

namespace App\Services;

use App\Models\PickingList;
use App\Models\ProductStock;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse → Retailer stock transfers.
 *
 * Recording a transfer only reserves stock at the source warehouse. The stock
 * itself moves when the picking list is completed.
 *
 * The physical movement is owned by {@see \App\Observers\PickingListObserver},
 * which fires on the picking list's status change and deducts the source stock,
 * releases the reservation, increments the destination and books the matching
 * StockTransaction rows. This service is deliberately thin: it drives those
 * status changes through Eloquent so both observers actually fire, which is what
 * the previous query-builder updates prevented.
 */
class StockTransferService
{
    /**
     * Complete a picking list and its linked transfer.
     */
    public function complete(PickingList $pickingList): void
    {
        DB::transaction(function () use ($pickingList) {
            // Only a transfer still awaiting completion can be completed. Guards
            // against a replayed request moving stock twice, and against
            // completing something that was cancelled.
            if (! $this->isOpen($pickingList)) {
                return;
            }

            $transfer = $this->resolveTransfer($pickingList);

            // Eloquent update -> PickingListObserver moves the stock.
            $pickingList->update(['status' => 'completed']);

            // Eloquent update -> StockTransferObserver posts the GL entry.
            if ($transfer && $transfer->status !== 'completed') {
                $transfer->update(['status' => 'completed']);
            }
        });
    }

    /**
     * Cancel a picking list, releasing the stock it had reserved.
     *
     * Nothing moved physically, so PickingListObserver is not involved here -
     * it only reacts to completion states.
     */
    public function cancel(PickingList $pickingList): void
    {
        DB::transaction(function () use ($pickingList) {
            // Cancelling a completed transfer would release a reservation that
            // has already been consumed, so only open transfers can be cancelled.
            if (! $this->isOpen($pickingList)) {
                return;
            }

            $transfer = $this->resolveTransfer($pickingList);

            $this->releaseReservations($pickingList);

            foreach ($pickingList->items as $item) {
                $item->update([
                    'quantity_picked' => 0,
                    'status'          => 'cancelled',
                ]);
            }

            $pickingList->update(['status' => 'cancelled']);

            if ($transfer && $transfer->status !== 'cancelled') {
                $transfer->update(['status' => 'cancelled']);
            }
        });
    }

    /**
     * A transfer still awaiting completion. 'pending' is accepted alongside
     * 'confirmed' so rows predating the rename remain actionable.
     */
    private function isOpen(PickingList $pickingList): bool
    {
        return in_array($pickingList->status, ['confirmed', 'pending'], true);
    }

    /**
     * The StockTransfer this picking list was generated for, if any.
     */
    private function resolveTransfer(PickingList $pickingList): ?StockTransfer
    {
        if ($pickingList->reference_type !== StockTransfer::class) {
            return null;
        }

        return StockTransfer::find($pickingList->reference_id);
    }

    /**
     * Give back the quantity this picking list reserved at the source warehouse
     * when it was created. Floored at zero so a repeated cancel cannot push the
     * reservation negative.
     */
    private function releaseReservations(PickingList $pickingList): void
    {
        foreach ($pickingList->items as $item) {
            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('location_id', $pickingList->from_location_id)
                ->where('location_type', $pickingList->from_location_type)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->reserved_quantity = max(0, (int) $stock->reserved_quantity - (int) $item->quantity_requested);
            $stock->save();
        }
    }
}
