<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PickingList;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class OrderService
{
    use HasErrorHandling;

    public function list()
    {
        return $this->getPaginatedOrEmpty(
            function() {
                \Log::info('OrderService: Starting to fetch orders...');
                $orders = Order::with(['customer', 'items', 'invoice'])->latest()->paginate(25);
                \Log::info('OrderService: Orders fetched successfully. Count: ' . $orders->count());
                return $orders;
            },
            'orders',
            25
        );
    }

    public function get(int $id): Order
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $order = Order::with(['customer', 'items', 'invoice'])->find($id);

                if (!$order) {
                    $this->logMissingData('order', $id);
                    throw new \App\Exceptions\DataNotFoundException('order', $id);
                }

                return $order;
            },
            'order',
            $id
        );
    }

    public function create(array $data): Order
    {
        return $this->handleServiceOperation(
            fn() => Order::create($data),
            'order'
        );
    }

    public function update(int $id, array $data): Order
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $order = $this->findOrFail(Order::class, $id, 'order');
                $order->update($data);
                return $order;
            },
            'order',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $order = $this->findOrFail(Order::class, $id, 'order');
                $order->delete();
            },
            'order',
            $id
        );
    }

    public function createWithItems(array $data): Order
    {
        return $this->handleServiceOperation(
            function() use ($data) {
                $products = $data['products'] ?? [];
                unset($data['products']);

                // Calculate total amount from all products
                $totalAmount = 0;
                foreach ($products as $product) {
                    $totalAmount += ($product['quantity'] ?? 0) * ($product['unit_price'] ?? 0);
                }

                // Extract fulfillment location from the first product (assuming all products use same location)
                $fulfillmentLocationId = null;
                $fulfillmentLocationType = null;
                if (!empty($products)) {
                    $firstProduct = $products[0];
                    $fulfillmentLocationId = $firstProduct['fulfillment_location_id'] ?? null;

                    // Map location type string to full class name for polymorphic relationship
                    $locationTypeString = $firstProduct['fulfillment_location_type'] ?? null;
                    $fulfillmentLocationType = match ($locationTypeString) {
                        'warehouse' => \App\Models\Warehouse::class,
                        'retailer' => \App\Models\Retailer::class,
                        default => null,
                    };
                }

                // Create order with fulfillment location and total amount
                $orderData = array_merge($data, [
                    'total_amount' => $totalAmount,
                    'fulfillment_location_id' => $fulfillmentLocationId,
                    'fulfillment_location_type' => $fulfillmentLocationType,
                    'status' => 'pending', // Set default status
                ]);

                $order = Order::create($orderData);

                // Create order items
                foreach ($products as $product) {
                    // Map location type string to full class name for polymorphic relationship
                    $locationTypeString = $product['fulfillment_location_type'] ?? null;
                    $locationType = match ($locationTypeString) {
                        'warehouse' => \App\Models\Warehouse::class,
                        'retailer' => \App\Models\Retailer::class,
                        default => null,
                    };

                    $productModel = \App\Models\Product::find($product['product_id']);

                    // Capture what the goods cost us right now. Without this the
                    // line would be re-costed against products.purchase_price at
                    // render time, so any later goods receipt would rewrite the
                    // profit on an order that was already closed.
                    $unitCost = $productModel
                        ? app(\App\Services\Pricing\ProductCostService::class)
                            ->costAtOrLegacy($productModel)
                        : 0.0;

                    // And which price row produced the figure charged, so
                    // "why was it that price?" stays answerable afterwards.
                    $priceListItemId = null;
                    if ($productModel) {
                        $resolved = app(\App\Services\Pricing\PriceResolver::class)->forSale(
                            $productModel,
                            new \App\Services\Pricing\PriceContext(
                                customer: $order->customer,
                                salesChannel: $order->salesChannel,
                                quantity: (int) $product['quantity'],
                            )
                        );
                        $priceListItemId = $resolved?->priceListItemId;
                    }

                    $order->items()->create([
                        'product_id' => $product['product_id'],
                        'location_id' => $product['fulfillment_location_id'],
                        'location_type' => $locationType,
                        'quantity' => $product['quantity'],
                        'unit_price' => $product['unit_price'],
                        'unit_cost' => $unitCost,
                        'price_list_item_id' => $priceListItemId,
                        'subtotal' => $product['quantity'] * $product['unit_price'],
                    ]);
                }

                return $order->load(['customer', 'items']);
            },
            'order'
        );
    }

    public function confirm(Order $order): PickingList
    {
        return $this->handleServiceOperation(
            function() use ($order) {
                // Check if order is already confirmed
                if ($order->status === 'confirmed') {
                    throw new \Exception('Order is already confirmed.');
                }

                // Check if picking list already exists
                $existingPickingList = PickingList::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->first();
                if ($existingPickingList) {
                    throw new \Exception('Picking list already exists for this order.');
                }

                // Create picking list
                $pickingList = PickingList::create([
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'from_location_type' => $order->fulfillment_location_type,
                    'from_location_id' => $order->fulfillment_location_id,
                    'to_location_type' => \App\Models\Customer::class,
                    'to_location_id' => $order->customer_id,
                    'picking_date' => now(),
                    'status' => 'pending',
                ]);

                // Create picking list items and reserve stock
                foreach ($order->items as $item) {
                    $pickingList->items()->create([
                        'product_id' => $item->product_id,
                        'quantity_requested' => $item->quantity,
                        'quantity_picked' => 0,
                    ]);

                    // Reserve stock at the fulfillment location
                    $stock = \App\Models\ProductStock::firstOrNew([
                        'product_id' => $item->product_id,
                        'location_id' => $order->fulfillment_location_id,
                        'location_type' => $order->fulfillment_location_type,
                    ], ['quantity' => 0, 'reserved_quantity' => 0]);

                    // Increment reserved quantity
                    $stock->reserved_quantity = ($stock->reserved_quantity ?? 0) + $item->quantity;
                    $stock->save();
                }

                // Update order status
                $order->update(['status' => 'confirmed']);

                return $pickingList->load(['items.product', 'fromLocation', 'toLocation']);
            },
            'picking list'
        );
    }

    /**
     * Get filtered orders with pagination
     */
    public function getFilteredOrders(array $filters = [], int $perPage = 20)
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = Order::with(['customer', 'items', 'invoice']);

                // Apply search filter
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('id', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($customerQuery) use ($search) {
                              $customerQuery->where('name', 'like', "%{$search}%")
                                           ->orWhere('email', 'like', "%{$search}%");
                          });
                    });
                }

                // Apply status filter
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                // Apply customer filter
                if (!empty($filters['customer_id'])) {
                    $query->where('customer_id', $filters['customer_id']);
                }

                // Apply date filters
                if (!empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }

                // Whitelisted, or an unknown column takes the whole listing
                // down into the empty-paginator fallback.
                [$sortField, $sortDirection] = $this->sortFrom($filters, $this->getSortOptions());
                $query->orderBy($sortField, $sortDirection);

                return $query->paginate($perPage);
            },
            'orders',
            $perPage,
            $filters
        );
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ]
            ],
            'customer_id' => [
                'type' => 'select',
                'label' => 'Customer',
                'options' => \App\Models\Customer::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'From Date',
                'placeholder' => 'Select start date'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'To Date',
                'placeholder' => 'Select end date'
            ]
        ];
    }

    /**
     * Get sort options for the view
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created Date',
            'total_amount' => 'Total Amount',
            'status' => 'Status',
        ];
    }

    /**
     * Deduct stock for a completed order by creating/completing a picking list
     * This ensures consistent stock transaction handling via PickingList reference only
     */
    public function deductOrderStock(Order $order): void
    {
        $this->handleServiceOperation(
            function() use ($order) {
                // Ensure order is completed
                if ($order->status !== 'completed') {
                    throw new \Exception('Cannot deduct stock: Order is not completed');
                }

                // Check if picking list already exists
                $pickingList = \App\Models\PickingList::where('reference_type', \App\Models\Order::class)
                    ->where('reference_id', $order->id)
                    ->first();

                if (!$pickingList) {
                    // Create picking list for this order (for manual completion).
                    // Same shape as confirm() raises: picking_date is NOT NULL with
                    // no default, and the destination is the customer so the list
                    // lands on the same index as a normally confirmed order.
                    // picking_lists has no picking_type or notes column.
                    $pickingList = \App\Models\PickingList::create([
                        'reference_type' => \App\Models\Order::class,
                        'reference_id' => $order->id,
                        'from_location_type' => $order->fulfillment_location_type,
                        'from_location_id' => $order->fulfillment_location_id,
                        'to_location_type' => \App\Models\Customer::class,
                        'to_location_id' => $order->customer_id,
                        'picking_date' => now(),
                        'status' => 'pending',
                    ]);

                    // Create picking list items from order items
                    foreach ($order->items as $orderItem) {
                        \App\Models\PickingListItem::create([
                            'picking_list_id' => $pickingList->id,
                            'product_id' => $orderItem->product_id,
                            'quantity_requested' => $orderItem->quantity,
                            'quantity_picked' => $orderItem->quantity,
                            'status' => 'picked'
                        ]);
                    }
                }

                // Mark picking list as completed to trigger stock deduction via observer
                // This ensures all stock transactions use PickingList reference consistently
                if (!in_array($pickingList->status, ['completed', 'closed', 'verified'])) {
                    $pickingList->update([
                        'status' => 'completed',
                        'completed_at' => now()
                    ]);
                }
            },
            'stock deduction via picking list',
            $order->id
        );
    }
}
