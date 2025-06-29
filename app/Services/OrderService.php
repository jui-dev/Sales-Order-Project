<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderService
{
    public function list(): Collection
    {
        return Order::with(['customer', 'items'])->latest()->get();
    }

    public function get(int $id): Order
    {
        return Order::with(['customer', 'items'])->findOrFail($id);
    }

    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function update(int $id, array $data): Order
    {
        $order = Order::findOrFail($id);
        $order->update($data);
        return $order;
    }

    public function delete(int $id): void
    {
        $order = Order::findOrFail($id);
        $order->delete();
    }

    public function createWithItems(array $data): Order
    {
        return \DB::transaction(function () use ($data) {
            // Extract top-level order inputs
            $orderData = [
                'customer_id'   => $data['customer_id'],
                'status'        => $data['status'] ?? 'pending',
                'order_date'    => $data['order_date'] ?? now(),
                'notes'         => $data['notes'] ?? null,
                'total_amount'  => 0, // will be updated after items loop
            ];

            /** @var Order $order */
            $order = Order::create($orderData);

            $total = 0;
            $firstLocationType = null;
            $firstLocationId   = null;

            foreach ($data['products'] as $item) {
                // Validate stock availability at selected location before creating order
                $locTypeClass = match($item['fulfillment_location_type']) {
                    'warehouse' => \App\Models\Warehouse::class,
                    'retailer'  => \App\Models\Retailer::class,
                    default     => \App\Models\StockLocation::class,
                };

                $stockRow = \App\Models\ProductStock::where('product_id', $item['product_id'])
                    ->where('location_type', $locTypeClass)
                    ->where('location_id', $item['fulfillment_location_id'])
                    ->first();

                $availableStock = $stockRow?->quantity - ($stockRow?->reserved_quantity ?? 0);

                if ($availableStock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for selected location (need {$item['quantity']}, available {$availableStock}).");
                }

                $subtotal = (float) $item['quantity'] * (float) $item['unit_price'];
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'location_id' => $item['fulfillment_location_id'],
                    'location_type' => $locTypeClass,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal'   => $subtotal,
                ]);
                $total += $subtotal;

                // capture first location details and ensure uniformity
                if ($firstLocationId === null) {
                    $firstLocationId   = $item['fulfillment_location_id'];
                    $firstLocationType = $locTypeClass;
                } elseif ($firstLocationId !== $item['fulfillment_location_id'] || $firstLocationType !== $locTypeClass) {
                    // Multiple locations – keep order-level location null to indicate mixed.
                    $firstLocationId = $firstLocationType = null;
                }
            }

            $updateData = ['total_amount' => $total];
            if ($firstLocationId !== null) {
                $updateData['fulfillment_location_id']   = $firstLocationId;
                $updateData['fulfillment_location_type'] = $firstLocationType;
            }

            $order->update($updateData);

            return $order->fresh(['items']);
        });
    }

    /**
     * Confirm an order – reserve stock & generate a picking list.
     */
    public function confirm(Order $order): \App\Models\PickingList
    {
        return \DB::transaction(function () use ($order) {
            // Reserve stock & record stock_transactions
            foreach ($order->items as $item) {
                $locationType = $item->location_type ?? \App\Models\Warehouse::class;
                $locationId   = $item->location_id ?? null;

                $productStock = \App\Models\ProductStock::where('product_id', $item->product_id)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->first();

                if (!$productStock || $productStock->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for product ID {$item->product_id} at selected location.");
                }

                // Reserve stock at the location (increment reserved_quantity) and persist
                $productStock->reserved_quantity = ($productStock->reserved_quantity ?? 0) + $item->quantity;
                $productStock->save();

                // Record stock transaction (reservation entry – still outbound but flagged via reference)
                \App\Models\StockTransaction::create([
                    'product_id'       => $item->product_id,
                    'location_id'      => $productStock->location_id,
                    'location_type'    => $productStock->location_type,
                    'quantity'         => $item->quantity,
                    'direction'        => 'outbound',
                    'transaction_type' => \App\Models\StockTransaction::TYPE_ORDER_FULFILLMENT,
                    'reference_type'   => Order::class, // reservation linked to order
                    'reference_id'     => $order->id,
                    'transaction_date' => now(),
                ]);
            }

            // Generate picking list (one per order)
            $pickingList = \App\Models\PickingList::create([
                'reference_type'     => Order::class,
                'reference_id'       => $order->id,
                'status'             => 'open',
                'picking_date'       => now(),
                // Source/destination locations (assumes uniform location across items)
                'from_location_id'   => $order->fulfillment_location_id,
                'from_location_type' => $order->fulfillment_location_type,
                // Destination is always customer for sales orders
                'to_location_id'     => $order->customer_id,
                'to_location_type'   => \App\Models\Customer::class,
            ]);

            // Generate a human readable picking_number now that we have an ID
            $pickingList->update([
                'picking_number' => 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($order->items as $item) {
                $pickingList->items()->create([
                    'product_id'         => $item->product_id,
                    'quantity_requested' => $item->quantity,
                    'quantity_picked'    => 0,
                    'status'             => 'pending',
                ]);
            }

            // Update order status
            $order->update(['status' => 'confirmed']);

            // Attach the newly created picking list on the order model for return
            $order->setRelation('pickingList', $pickingList);

            // Return picking list
            return $pickingList;
        });
    }
} 