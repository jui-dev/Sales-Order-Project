<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function list(): Collection
    {
        try {
            return Product::all();
        } catch (\Throwable $e) {
            // In UI-only or migration-incomplete scenarios, ensure a safe fallback
            return collect();
        }
    }

    public function get(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product;
    }

    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);
        $product->delete();
    }

    public function stockAnalysis(Product $product): array
    {
        // Completed supplies
        $totalSupplied = \App\Models\SupplyItem::where('product_id', $product->id)
            ->whereHas('supply', fn ($q) => $q->where('status', 'completed'))
            ->sum('quantity');

        // Completed orders (sold)
        $totalOrdered = \App\Models\OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('status', 'completed'))
            ->sum('quantity');

        // Pending supplies & orders
        $pendingSupplies = \App\Models\SupplyItem::where('product_id', $product->id)
            ->whereHas('supply', fn ($q) => $q->whereIn('status', ['pending', 'processing']))
            ->sum('quantity');

        $pendingOrders = \App\Models\OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'processing']))
            ->sum('quantity');

        // Current stock derived from ProductStock balances
        $currentStock = (int) $product->stockBalances()->sum('quantity');

        $stockData = [
            'product'           => $product,
            'current_stock'     => $currentStock,
            'total_supplied'    => $totalSupplied,
            'total_ordered'     => $totalOrdered,
            'projected_stock'   => $currentStock + $pendingSupplies - $pendingOrders,
            'pending_supplies'  => $pendingSupplies,
            'pending_orders'    => $pendingOrders,
            'stock_by_location' => $product->stockBalances()->with('location')->get()->each(function ($s) {
                // Provide alias used by Blade
                $s->setRelation('stockLocation', $s->location);
            }),
            'supplies'          => \App\Models\Supply::query()
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->with(['vendor'])
                ->get()
                ->map(function ($supply) use ($product) {
                    // Attach quantity & unit cost for this product only
                    $item = $supply->items->firstWhere('product_id', $product->id);
                    $supply->quantity   = $item?->quantity ?? 0;
                    $supply->unit_cost  = $item?->unit_cost ?? 0;
                    $supply->vendor_name= $supply->vendor?->name;
                    return $supply;
                }),
            'orders'            => \App\Models\Order::query()
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->with(['customer'])
                ->get()
                ->map(function ($order) use ($product) {
                    $item = $order->items->firstWhere('product_id', $product->id);
                    $order->quantity   = $item?->quantity ?? 0;
                    $order->unit_price = $item?->unit_price ?? 0;
                    $order->customer_name = $order->customer?->name;
                    return $order;
                }),
        ];

        return $stockData;
    }

    public function transactionHistory(Product $product): array
    {
        $totalSupplied = \App\Models\SupplyItem::where('product_id', $product->id)
            ->sum('quantity');
        $totalSold = \App\Models\OrderItem::where('product_id', $product->id)
            ->sum('quantity');

        // Transfers (stock transfers)
        $totalTransferred = \App\Models\StockTransferItem::where('product_id', $product->id)->sum('quantity');

        // Current balances
        $stockBalances = $product->stockBalances()->with('location')->get()->each(function ($s) {
            $s->setRelation('stockLocation', $s->location);
        });

        $currentTotalStock     = $stockBalances->sum('quantity');
        $currentReservedStock  = $stockBalances->sum('reserved_quantity');
        $currentAvailableStock = $currentTotalStock - $currentReservedStock;

        // Movements (stock transactions)
        $movements = \App\Models\StockTransaction::where('product_id', $product->id)
            ->with('location') // eager load polymorphic location (warehouse/retailer)
            ->orderByDesc('transaction_date')
            ->paginate(20);

        /* ------------------------------------------------------------------
         | Post-process each transaction so the Blade template has the
         | aliases/attributes it is expecting (movement_date, movement_type,
         | fromLocation, toLocation, status)
         |------------------------------------------------------------------*/
        $movements->getCollection()->transform(function ($txn) {
            /* --------------------------------------------------------------
             | movement_date : ensure Carbon instance available
             --------------------------------------------------------------*/
            $txn->movement_date = $txn->transaction_date ?? $txn->created_at ?? now();

            /* --------------------------------------------------------------
             | movement_type : map internal constants to legacy labels used
             | by the Blade ( supply_in / sale / transfer / adjustment )
             --------------------------------------------------------------*/
            $txn->movement_type = match ($txn->transaction_type) {
                \App\Models\StockTransaction::TYPE_STOCK_IN          => 'supply_in',
                \App\Models\StockTransaction::TYPE_ORDER_FULFILLMENT => 'sale',
                \App\Models\StockTransaction::TYPE_STOCK_TRANSFER    => 'transfer',
                default                                               => 'adjustment',
            };

            /* --------------------------------------------------------------
             | fromLocation / toLocation : derive based on direction
             | If direction == outbound  => FROM is current location
             | If direction == inbound   => TO   is current location
             --------------------------------------------------------------*/
            if ($txn->direction === 'outbound') {
                $txn->fromLocation = $txn->location; // warehouse/retailer
                $txn->toLocation   = null;           // will be shown as Customer/etc. in Blade fallback
            } else {
                $txn->fromLocation = null;           // Vendor/etc. handled by Blade
                $txn->toLocation   = $txn->location;
            }

            /* --------------------------------------------------------------
             | status : proxy status from the reference model (supply/order/
             | transfer) if available, else default to completed.
             --------------------------------------------------------------*/
            $status = 'completed';
            if ($txn->reference_type && $txn->reference_id) {
                try {
                    $refModel = app($txn->reference_type)::find($txn->reference_id);
                    if ($refModel && property_exists($refModel, 'status')) {
                        $status = $refModel->status ?? $status;
                    }
                } catch (\Throwable $e) {
                    // silent – keep default status
                }
            }
            $txn->status = $status;

            return $txn;
        });

        // Picking lists (using PickingListItem model maybe) – keep empty for now if models missing
        $pickingLists = \App\Models\PickingList::query()
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->with(['fromLocation', 'toLocation'])
            ->latest()
            ->take(10)
            ->get();

        return compact(
            'product',
            'totalSupplied',
            'totalSold',
            'totalTransferred',
            'currentTotalStock',
            'currentAvailableStock',
            'currentReservedStock',
            'stockBalances',
            'movements',
            'pickingLists',
        );
    }
} 