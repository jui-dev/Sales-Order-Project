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
     * Mark supply as completed.
     *
     * Stock should NOT be posted at this stage. The stock movement will be
     * handled when the corresponding GRN is marked as "posted" via the
     * GrnService. Here we simply update the status to completed and ensure
     * a GRN stub exists for the receiving team.
     */
    public function complete(int $id): Supply
    {
        return \DB::transaction(function () use ($id) {
            /** @var Supply $supply */
            $supply = Supply::with(['grn'])->findOrFail($id);

            // If it's already completed we are done.
            if ($supply->status === 'completed') {
                return $supply;
            }

            // Update the status first – **no stock posting here**
            $supply->update(['status' => 'completed']);

            // Ensure there is a GRN linked to this supply so that the
            // warehouse can go through the receiving verification flow.
            \App\Models\Grn::firstOrCreate(
                ['supply_id' => $supply->id],
                [
                    'received_date' => now(),
                    'status'        => 'draft',
                ]
            );

            return $supply->load(['grn']);
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