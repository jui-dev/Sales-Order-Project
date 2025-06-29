<?php

namespace App\Services;

use App\Models\Grn;
use App\Models\Supply;
use Illuminate\Support\Facades\DB;

class GrnService
{
    /**
     * Transition a GRN to the supplied next status. When the status becomes
     * "posted" the corresponding stock transactions will be booked and the
     * product stock levels updated.
     */
    public function transitionStatus(int $grnId, string $toStatus): Grn
    {
        return DB::transaction(function () use ($grnId, $toStatus) {
            /** @var Grn $grn */
            $grn   = Grn::with(['supply.vendor', 'supply.warehouse', 'supply.items'])->findOrFail($grnId);
            $from  = $grn->status;

            // Early-out if already at desired state
            if ($from === $toStatus) {
                return $grn;
            }

            // Update the status first
            $grn->update(['status' => $toStatus]);

            // Only run stock posting logic when we reach the final state
            if ($toStatus === 'posted') {
                $this->postStock($grn);
            }

            return $grn;
        });
    }

    /**
     * Perform stock movements once the GRN is posted.
     *
     * We increase stock in the destination warehouse and create matching
     * stock transactions / transfer records so that the entire movement is
     * auditable.
     */
    private function postStock(Grn $grn): void
    {
        // Guard against double-posting
        if ($grn->posted_at ?? false) {
            return;
        }

        $supply    = $grn->supply;
        $warehouse = $supply->warehouse;

        /* ------------------------------------------------------------
         * Create (or fetch) a StockTransfer that represents the inbound
         * movement from Vendor → Warehouse. Doing this upfront allows us
         * to avoid running firstOrCreate inside the loop.
         * ------------------------------------------------------------ */
        $transfer = \App\Models\StockTransfer::firstOrCreate([
            'from_location_id'   => $supply->vendor_id,
            'from_location_type' => \App\Models\Vendor::class,
            'to_location_id'     => $supply->warehouse_id,
            'to_location_type'   => get_class($warehouse),
            'transfer_date'      => $grn->received_date ?? now(),
        ], [
            'status' => 'completed',
            'notes'  => 'Auto-generated from GRN #'.$grn->id,
        ]);

        foreach ($supply->items as $item) {
            // Update / create product stock for the warehouse
            $stock = \App\Models\ProductStock::firstOrCreate([
                'product_id'    => $item->product_id,
                'location_id'   => $supply->warehouse_id,
                'location_type' => get_class($warehouse),
            ]);
            $stock->quantity += $item->quantity;
            $stock->save();

            // Create stock transaction ledger
            \App\Models\StockTransaction::create([
                'product_id'       => $item->product_id,
                'location_id'      => $supply->warehouse_id,
                'location_type'    => get_class($warehouse),
                'quantity'         => $item->quantity,
                'direction'        => 'inbound',
                'transaction_type' => \App\Models\StockTransaction::TYPE_STOCK_IN,
                'reference_type'   => Grn::class,
                'reference_id'     => $grn->id,
                'transaction_date' => now(),
            ]);

            // Persist transfer item row (linked to the transfer created above)
            $transfer->items()->create([
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
            ]);
        }

        // Mark supply as completed
        if ($supply->status !== 'completed') {
            $supply->update(['status' => 'completed']);
        }

        // Add a simple posted_at timestamp column on-the-fly (if exists) to
        // guard against double-posting. If the column does not exist, we
        // quietly ignore — this keeps the logic flexible.
        if ($grn->getConnection()->getSchemaBuilder()->hasColumn($grn->getTable(), 'posted_at')) {
            $grn->forceFill(['posted_at' => now()])->saveQuietly();
        }
    }
} 