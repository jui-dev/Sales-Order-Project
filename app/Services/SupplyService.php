<?php

namespace App\Services;

use App\Models\Supply;
use Illuminate\Database\Eloquent\Collection;

class SupplyService
{
    public function list(): Collection
    {
        return Supply::with(['vendor', 'warehouse', 'items', 'grn'])->latest()->get();
    }

    public function get(int $id): Supply
    {
        return Supply::with(['vendor', 'warehouse', 'items', 'grn'])->findOrFail($id);
    }

    public function create(array $data): Supply
    {
        return Supply::create($data);
    }

    /**
     * Create a supply together with its nested items array (same structure as validated request)
     * while automatically calculating and persisting the total_cost.
     */
    public function createWithItems(array $data): Supply
    {
        return \DB::transaction(function () use ($data) {
            $items  = $data['products'] ?? [];

            unset($data['products']);

            // calculate total cost
            $total = collect($items)->sum(function ($itm) {
                return ($itm['quantity'] ?? 0) * ($itm['unit_cost'] ?? 0);
            });
            $data['total_cost'] = $total;

            /** @var Supply $supply */
            $supply = Supply::create($data);

            foreach ($items as $item) {
                $supply->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_cost'  => $item['unit_cost'],
                    'subtotal'   => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            return $supply->load(['vendor', 'warehouse', 'items']);
        });
    }

    /**
     * Mark supply as completed and post stock transactions.
     */
    public function complete(int $id): Supply
    {
        return \DB::transaction(function () use ($id) {
            /** @var Supply $supply */
            $supply = Supply::with(['items', 'warehouse'])->findOrFail($id);

            if ($supply->status === 'completed') {
                return $supply;
            }

            /* ------------------------------------------------------------
             * Create / fetch a stock transfer record representing the
             * movement of inventory from the vendor to the warehouse.
             * We create it BEFORE looping through items so that we don't
             * end up running firstOrCreate inside the loop (which could
             * lead to unnecessary DB queries).
             * ------------------------------------------------------------ */
            $transfer = \App\Models\StockTransfer::firstOrCreate([
                'from_location_id'   => $supply->vendor_id,
                'from_location_type' => \App\Models\Vendor::class,
                'to_location_id'     => $supply->warehouse_id,
                'to_location_type'   => get_class($supply->warehouse),
                'transfer_date'      => $supply->supply_date,
            ], [
                'status' => 'completed',
                'notes'  => 'Auto-generated from Supply #'.$supply->id,
            ]);

            // Post stock for each item
            foreach ($supply->items as $item) {
                // Update / create product stock for the warehouse
                $stock = \App\Models\ProductStock::firstOrCreate([
                    'product_id'   => $item->product_id,
                    'location_id'  => $supply->warehouse_id,
                    'location_type'=> get_class($supply->warehouse),
                ]);
                $stock->quantity += $item->quantity;
                $stock->save();

                // Write stock transaction ledger
                \App\Models\StockTransaction::create([
                    'product_id'       => $item->product_id,
                    'location_id'      => $supply->warehouse_id,
                    'location_type'    => get_class($supply->warehouse),
                    'quantity'         => $item->quantity,
                    'direction'        => 'inbound',
                    'transaction_type' => \App\Models\StockTransaction::TYPE_STOCK_IN,
                    'reference_type'   => Supply::class,
                    'reference_id'     => $supply->id,
                    'transaction_date' => now(),
                ]);

                // Persist transfer item row (linked to the transfer created above)
                $transfer->items()->create([
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                ]);
            }

            $supply->update(['status' => 'completed']);

            /* ------------------------------------------------------------
             * Auto-generate a GRN (Goods Receipt Note) for audit trail.
             * We only create it if one doesn\'t already exist for this supply.
             * ------------------------------------------------------------ */
            \App\Models\Grn::firstOrCreate([
                'supply_id' => $supply->id,
            ], [
                'received_date' => now(),
                'status'        => 'draft',
            ]);

            return $supply;
        });
    }

    public function confirm(int $id): Supply
    {
        return \DB::transaction(function () use ($id) {
            /** @var Supply $supply */
            $supply = Supply::with(['grn'])->findOrFail($id);

            // If already confirmed we simply return early.
            if ($supply->status === 'confirmed') {
                return $supply;
            }

            // Update supply status to confirmed
            $supply->update(['status' => 'confirmed']);

            /* ------------------------------------------------------------
             * Auto-generate a GRN (Goods Receipt Note) stub so that the
             * warehouse team can start the receiving verification flow.
             * We only create it if one doesn\'t already exist for this supply.
             * ------------------------------------------------------------ */
            \App\Models\Grn::firstOrCreate([
                'supply_id' => $supply->id,
            ], [
                'received_date' => now(),
                'status'        => 'draft',
            ]);

            return $supply->load(['grn']);
        });
    }

    public function update(int $id, array $data): Supply
    {
        $model = Supply::findOrFail($id);
        $model->update($data);
        return $model;
    }

    public function delete(int $id): void
    {
        $model = Supply::findOrFail($id);
        $model->delete();
    }
} 