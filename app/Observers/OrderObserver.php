<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\PickingList;
use App\Models\ProductStock;
use App\Models\PickingListItem;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    /**
     * When order status becomes confirmed, generate picking list if retailer fulfilment.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== 'confirmed') {
            return;
        }

        // Ensure fulfilment location is a retailer
        if ($order->fulfillment_location_type !== \App\Models\Retailer::class) {
            return; // warehouse orders handled elsewhere
        }

        // Skip if picking list already exists
        $exists = PickingList::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->exists();
        if ($exists) {
            return;
        }

        // Build picking list & reserve stock
        DB::transaction(function () use ($order) {
            $retailer = $order->fulfillmentLocation; // Retailer model
            $customer = $order->customer;

            /** @var PickingList $list */
            $list = PickingList::create([
                'reference_type'     => Order::class,
                'reference_id'       => $order->id,
                'from_location_id'   => $retailer->id,
                'from_location_type' => \App\Models\Retailer::class,
                'to_location_id'     => $customer->id,
                'to_location_type'   => \App\Models\Customer::class,
                'status'             => 'pending',
                'picking_date'       => now(),
            ]);
            $list->picking_number = 'PL-' . str_pad($list->id, 6, '0', STR_PAD_LEFT);
            $list->saveQuietly();

            foreach ($order->orderItems as $item) {
                // Reserve stock at retailer
                $stock = ProductStock::firstOrNew([
                    'product_id'    => $item->product_id,
                    'location_id'   => $retailer->id,
                    'location_type' => \App\Models\Retailer::class,
                ], ['quantity' => 0, 'reserved_quantity' => 0]);

                // Increment reserved quantity
                $stock->reserved_quantity = ($stock->reserved_quantity ?? 0) + $item->quantity;
                $stock->save();

                // Create picking list line
                $list->items()->create([
                    'product_id'         => $item->product_id,
                    'quantity_requested' => $item->quantity,
                    'quantity_picked'    => 0,
                    'status'             => 'pending',
                ]);
            }
        });
    }
} 