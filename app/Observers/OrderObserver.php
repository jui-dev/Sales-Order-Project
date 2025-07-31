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
        if (! $order->wasChanged('status')) {
            return; // status unchanged – nothing to do
        }

        /* --------------------------------------------------------------
         | 1. When status == confirmed     ➜ Generate picking list (retailer)
         | 2. When status == confirmed OR completed ➜ Generate invoice
         --------------------------------------------------------------*/

        if ($order->status === 'confirmed') {
            // Calculate gross profit for each product in the order
            $this->calculateGrossProfitForOrder($order);

            // ----------------------------------------------------------
            // Picking list only for retailer fulfilment
            // ----------------------------------------------------------
            if ($order->fulfillment_location_type === \App\Models\Retailer::class) {
                // Skip if picking list already exists
                $exists = PickingList::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->exists();
                if (! $exists) {
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
        }

        // --------------------------------------------------------------
        // Generate invoice when order becomes COMPLETED only
        // --------------------------------------------------------------
        if ($order->status === 'completed') {
            try {
                app(\App\Services\InvoiceService::class)->generateFromOrder($order);
            } catch (\Throwable $e) {
                \Log::error('Failed to auto-generate invoice for Order '.$order->id.': '.$e->getMessage());
            }
        }
    }

    /**
     * Calculate gross profit for each product in the order when order is confirmed.
     * Formula: Gross Profit = Revenue (Sales) - Cost of Goods Sold (COGS)
     */
    private function calculateGrossProfitForOrder(Order $order): void
    {
        // Load order items with products to get purchase prices
        $order->load('orderItems.product');

        foreach ($order->orderItems as $item) {
            $product = $item->product;
            
            if (!$product) {
                continue; // Skip if product not found
            }

            // Calculate Revenue (Sales) for this item
            $revenue = $item->unit_price * $item->quantity;
            
            // Calculate Cost of Goods Sold (COGS) for this item
            $unitCost = $product->purchase_price ?? 0;
            $cogs = $unitCost * $item->quantity;
            
            // Calculate Gross Profit for this item
            $grossProfit = $revenue - $cogs;
            
            // Update the product's gross_profit field
            // Note: This will be the gross profit per unit, not total
            if ($item->quantity > 0) {
                $grossProfitPerUnit = $grossProfit / $item->quantity;
                $product->gross_profit = round($grossProfitPerUnit, 2);
                $product->saveQuietly(); // Use saveQuietly to avoid triggering observers
            }
        }
    }
} 