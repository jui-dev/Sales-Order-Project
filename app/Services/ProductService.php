<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Product::class, 'products');
    }

    public function get(int $id): Product
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $product = Product::with([
                    'supplyItems.supply.vendor',
                    'orderItems.order.customer',
                    'stockBalances.location',
                    'stockTransactions.location',
                    'stockTransactions.reference',
                    'category',
                ])->find($id);

                if (!$product) {
                    $this->logMissingData('product', $id);
                    throw new \App\Exceptions\DataNotFoundException('product', $id);
                }

                return $product;
            },
            'product',
            $id
        );
    }

    public function create(array $data): Product
    {
        return $this->handleServiceOperation(
            function () use ($data) {
                $vendorIds = $this->pullVendorIds($data);

                return DB::transaction(function () use ($data, $vendorIds) {
                    $product = Product::create($data);
                    $this->syncVendors($product, $vendorIds);

                    return $product;
                });
            },
            'product'
        );
    }

    public function update(int $id, array $data): Product
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $vendorIds = $this->pullVendorIds($data);

                return DB::transaction(function () use ($id, $data, $vendorIds) {
                    $product = $this->findOrFail(Product::class, $id, 'product');
                    $product->update($data);
                    $this->syncVendors($product, $vendorIds);

                    return $product;
                });
            },
            'product',
            $id
        );
    }

    /**
     * Lift vendor_ids out of the attribute array - it is a relation, not a column.
     *
     * Returns null when the key is absent, which means "leave the existing
     * assignments alone"; an empty array means "detach them all".
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>|null
     */
    private function pullVendorIds(array &$data): ?array
    {
        if (! array_key_exists('vendor_ids', $data)) {
            return null;
        }

        $ids = $data['vendor_ids'];
        unset($data['vendor_ids']);

        return array_map('intval', $ids ?? []);
    }

    /**
     * Attach the product to its vendors without disturbing agreed costs.
     *
     * syncWithoutDetaching would leave removed vendors behind, and a plain
     * sync() would rewrite the pivot - so detach only what was dropped and
     * attach only what is new, leaving every surviving row's unit_cost intact.
     *
     * @param  array<int, int>|null  $vendorIds
     */
    private function syncVendors(Product $product, ?array $vendorIds): void
    {
        if ($vendorIds === null) {
            return;
        }

        $existing = $product->vendors()->pluck('vendors.id')->all();
        $added = array_diff($vendorIds, $existing);

        $product->vendors()->detach(array_diff($existing, $vendorIds));
        $product->vendors()->attach($added);

        // Simple mode prices a product once, whoever supplies it. A vendor
        // attached after the price was set has nothing on their own list, so a
        // purchase order to them would open with an empty cost - quote them at
        // what the product already costs instead.
        if ($added && config('pricing.simple_mode', false)) {
            $simple = app(\App\Services\Pricing\SimplePricingService::class);

            foreach (\App\Models\Vendor::whereIn('id', $added)->get() as $vendor) {
                $simple->backfillVendor($product->refresh(), $vendor);
            }
        }
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $product = $this->findOrFail(Product::class, $id, 'product');
                $product->delete();
            },
            'product',
            $id
        );
    }

    /**
     * Recalculate available_stocks for all products
     * This method considers both base stock from product_stocks table
     * and adjustments from stock transactions (including returns)
     */
    public function recalculateAllProductStocks(): array
    {
        return $this->handleServiceOperation(
            function() {
                $products = Product::all();
                $results = [];

                foreach ($products as $product) {
                    $this->recalculateProductStock($product);
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'new_stock' => $product->available_stocks,
                    ];
                }

                return $results;
            },
            'product stock calculation'
        );
    }

    /**
     * Recalculate available_stocks for a specific product
     * Only considers internal locations (warehouses and retailers)
     * Uses stock_transactions to ensure accurate stock levels
     */
    public function recalculateProductStock(Product $product): void
    {
        // Start a transaction to ensure data consistency
        DB::transaction(function() use ($product) {
            // First, reset all product_stocks to 0
            DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->update(['quantity' => 0]);

            // Get all completed stock transactions
            $transactions = DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->where('status', 'completed')
                ->orderBy('transaction_date')
                ->get();

            // Process each transaction to rebuild stock levels
            foreach ($transactions as $transaction) {
                $quantity = $transaction->quantity;
                if ($transaction->direction === 'outbound') {
                    $quantity = -$quantity;
                }

                // Update stock for the location
                DB::table('product_stocks')
                    ->where('product_id', $product->id)
                    ->where('location_id', $transaction->location_id)
                    ->where('location_type', $transaction->location_type)
                    ->increment('quantity', $quantity);
            }

            // Calculate available stock from product_stocks accounting for reservations
            $stockData = DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->whereIn('location_type', [
                    'App\\Models\\Warehouse',
                    'App\\Models\\Retailer'
                ])
                ->selectRaw('SUM(quantity) as total_stock, SUM(COALESCE(reserved_quantity, 0)) as reserved_stock')
                ->first();

            $totalStock = $stockData->total_stock ?? 0;
            $reservedStock = $stockData->reserved_stock ?? 0;
            $availableStock = max(0, $totalStock - $reservedStock);

            // Update the product's available_stocks
            $product->update(['available_stocks' => $availableStock]);
        });
    }

    /**
     * Get filtered products with pagination
     */
    public function getFilteredProducts(array $filters = [], int $perPage = 20)
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                // Price is no longer a column on products - it lives in dated
                // rows on the default sale list - so the listing joins it in
                // order to show, filter and sort by it.
                $query = Product::with('category')->withCurrentPricing();

                // Apply search filter
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('products.name', 'like', "%{$search}%")
                          ->orWhere('products.sku', 'like', "%{$search}%")
                          ->orWhere('products.description', 'like', "%{$search}%");
                    });
                }

                // Apply category and subcategory filters
                if (!empty($filters['category_id']) && !empty($filters['subcategory_id'])) {
                    // If both category and subcategory are selected, filter by subcategory
                    $query->where('category_id', $filters['subcategory_id']);
                } elseif (!empty($filters['category_id'])) {
                    // If only category is selected, filter by category and its subcategories
                    $category = \App\Models\ProductCategory::find($filters['category_id']);
                    if ($category) {
                        $subcategoryIds = $category->subcategories->pluck('id')->toArray();
                        $categoryIds = array_merge([$category->id], $subcategoryIds);
                        $query->whereIn('category_id', $categoryIds);
                    } else {
                        $query->where('category_id', $filters['category_id']);
                    }
                } elseif (!empty($filters['subcategory_id'])) {
                    // If only subcategory is selected, filter by that subcategory
                    $query->where('category_id', $filters['subcategory_id']);
                }

                // Apply price filters against the joined in-force price.
                if (!empty($filters['price_min'])) {
                    $query->where('current_price_row.unit_price', '>=', $filters['price_min']);
                }
                if (!empty($filters['price_max'])) {
                    $query->where('current_price_row.unit_price', '<=', $filters['price_max']);
                }

                // Apply stock filters
                if (!empty($filters['stock_min'])) {
                    $query->where('products.available_stocks', '>=', $filters['stock_min']);
                }
                if (!empty($filters['stock_max'])) {
                    $query->where('products.available_stocks', '<=', $filters['stock_max']);
                }

                // Apply sorting. Sorting by price means the joined column;
                // everything else is a real column on products, and is
                // qualified so the join cannot make it ambiguous.
                [$sortField, $sortDirection] = $this->sortFrom($filters, $this->getSortOptions());

                $query->orderBy(
                    $sortField === 'selling_price' ? 'current_price_row.unit_price' : "products.{$sortField}",
                    $sortDirection,
                );

                $products = $query->paginate($perPage);

                $this->attachRealisedProfit($products);

                return $products;
            },
            'products',
            $perPage,
            $filters
        );
    }

    /**
     * Hang month-to-date realised profit off each product on the page.
     *
     * The margin column beside it is a catalogue figure - list price less the
     * average cost of the stock on hand - and never moves when goods are sold
     * or sent back. This is the other question: what these products have
     * actually earned, net of returns, according to the ledger.
     *
     * One grouped query for the whole page. A per-row accessor would be twenty
     * queries a page, and the products listing is the most-visited screen in
     * the system.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator $products
     */
    private function attachRealisedProfit($products): void
    {
        $ids = $products->getCollection()->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $profit = app(ReportService::class)->realisedProfitByProduct(
            $ids,
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $products->getCollection()->each(function (Product $product) use ($profit) {
            // Absent, not zero: a product with no posted sale this month has
            // not earned nothing, it has not sold. The view says so.
            $product->realised_profit = $profit[$product->id]['profit'] ?? null;
            $product->realised_revenue = $profit[$product->id]['revenue'] ?? null;

            // Neither is a column. Syncing them into the original state means a
            // later save() cannot see them as dirty and try to write them.
            $product->syncOriginal();
        });
    }

    /**
     * Get transaction history for a product
     */
    public function transactionHistory(Product $product): array
    {
        return $this->handleServiceOperation(
            function() use ($product) {
                // Simplified version to isolate the issue
                $totalSupplied = 0;
                $totalSold = 0;
                $totalTransferred = 0;

                try {
                    // Get total supplied quantity (completed supplies)
                    $totalSupplied = DB::table('supply_items')
                        ->join('supplies', 'supply_items.supply_id', '=', 'supplies.id')
                        ->where('supply_items.product_id', $product->id)
                        ->where('supplies.status', 'completed')
                        ->sum('supply_items.quantity');
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting total supplied', ['error' => $e->getMessage()]);
                }

                try {
                    // Get total sold quantity (completed orders)
                    $totalSold = DB::table('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('order_items.product_id', $product->id)
                        ->where('orders.status', 'completed')
                        ->sum('order_items.quantity');
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting total sold', ['error' => $e->getMessage()]);
                }

                try {
                    // Get total transferred quantity (completed stock transfers)
                    $totalTransferred = DB::table('stock_transfer_items')
                        ->join('stock_transfers', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
                        ->where('stock_transfer_items.product_id', $product->id)
                        ->where('stock_transfers.status', 'completed')
                        ->sum('stock_transfer_items.quantity');
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting total transferred', ['error' => $e->getMessage()]);
                }

                // Get current stock balances by location
                $stockBalances = collect();
                try {
                    $stockBalances = DB::table('product_stocks')
                        ->leftJoin('warehouses', function($join) {
                            $join->on('product_stocks.location_id', '=', 'warehouses.id')
                                 ->on('product_stocks.location_type', '=', DB::raw("'App\\\\Models\\\\Warehouse'"));
                        })
                        ->leftJoin('retailers', function($join) {
                            $join->on('product_stocks.location_id', '=', 'retailers.id')
                                 ->on('product_stocks.location_type', '=', DB::raw("'App\\\\Models\\\\Retailer'"));
                        })
                        ->where('product_stocks.product_id', $product->id)
                        ->select(
                            'product_stocks.*',
                            DB::raw('COALESCE(warehouses.name, retailers.name) as location_name'),
                            DB::raw('CASE WHEN warehouses.id IS NOT NULL THEN "warehouse" 
                                     WHEN retailers.id IS NOT NULL THEN "retailer" 
                                     ELSE "unknown" END as location_type'),
                            DB::raw('(product_stocks.quantity - COALESCE(product_stocks.reserved_quantity, 0)) as available_quantity')
                        )
                        ->get()
                        ->map(function ($balance) {
                            // Create a stockLocation object for the view
                            $balance->stockLocation = (object) [
                                'name' => $balance->location_name,
                                'location_type' => $balance->location_type,
                            ];
                            return $balance;
                        });
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting stock balances', ['error' => $e->getMessage()]);
                }

                // Ensure stockBalances is always a collection
                if (!$stockBalances instanceof \Illuminate\Support\Collection) {
                    $stockBalances = collect();
                }

                // Get all stock movements (transactions)
                $movements = collect();
                try {
                    $movements = StockTransaction::with(['location', 'reference'])
                        ->where('product_id', $product->id)
                        ->latest('transaction_date')
                        ->get()
                        ->map(function ($transaction) {
                            // Determine movement type based on transaction type
                            $movementType = 'adjustment'; // default
                            switch($transaction->transaction_type) {
                                case 'stock_in':
                                    $movementType = 'supply_in';
                                    break;
                                case 'order_fulfillment':
                                    $movementType = 'sale';
                                    break;
                                case 'stock_transfer':
                                    $movementType = 'transfer';
                                    break;
                                case 'customer_return':
                                    $movementType = 'customer_return';
                                    break;
                                case 'vendor_return':
                                    $movementType = 'vendor_return';
                                    break;
                                case 'retailer_return':
                                    $movementType = 'retailer_return';
                                    break;
                            }

                            // Determine from/to locations based on direction and transaction type
                            $fromLocation = null;
                            $toLocation = null;

                            if ($transaction->direction === 'outbound') {
                                $fromLocation = $transaction->location;
                                // For sales, destination is customer
                                if ($transaction->transaction_type === 'order_fulfillment') {
                                    $toLocation = null; // Customer (not a location in our system)
                                } else {
                                    $toLocation = null; // Will be determined by reference
                                }
                            } else {
                                $toLocation = $transaction->location;
                                // For supplies, source is vendor
                                if ($transaction->transaction_type === 'stock_in') {
                                    $fromLocation = null; // Vendor (not a location in our system)
                                } else {
                                    $fromLocation = null; // Will be determined by reference
                                }
                            }

                            // For transfers, determine from/to from the reference
                            if ($transaction->transaction_type === 'stock_transfer' && $transaction->reference) {
                                $fromLocation = $transaction->reference->fromLocation;
                                $toLocation = $transaction->reference->toLocation;
                            }

                            return (object) [
                                'id' => $transaction->id,
                                'movement_date' => $transaction->transaction_date,
                                'movement_type' => $movementType,
                                'direction' => $transaction->direction,
                                'quantity' => $transaction->quantity,
                                'fromLocation' => $fromLocation,
                                'toLocation' => $toLocation,
                                'reference_type' => $transaction->reference_type ? (strpos($transaction->reference_type, '\\') !== false ? substr($transaction->reference_type, strrpos($transaction->reference_type, '\\') + 1) : $transaction->reference_type) : null,
                                'reference_id' => $transaction->reference_id,
                                'status' => $transaction->status ?? 'completed',
                                'notes' => $transaction->notes,
                            ];
                        });
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting movements', ['error' => $e->getMessage()]);
                    $movements = collect(); // Ensure it's always a collection
                }

                // Ensure movements is always a collection
                if (!$movements instanceof \Illuminate\Support\Collection) {
                    $movements = collect();
                }

                // Get related picking lists
                $pickingLists = collect();
                try {
                    $pickingLists = DB::table('picking_list_items')
                        ->join('picking_lists', 'picking_list_items.picking_list_id', '=', 'picking_lists.id')
                        ->leftJoin('warehouses as from_warehouses', function($join) {
                            $join->on('picking_lists.from_location_id', '=', 'from_warehouses.id')
                                 ->on('picking_lists.from_location_type', '=', DB::raw("'App\\\\Models\\\\Warehouse'"));
                        })
                        ->leftJoin('retailers as from_retailers', function($join) {
                            $join->on('picking_lists.from_location_id', '=', 'from_retailers.id')
                                 ->on('picking_lists.from_location_type', '=', DB::raw("'App\\\\Models\\\\Retailer'"));
                        })
                        ->leftJoin('warehouses as to_warehouses', function($join) {
                            $join->on('picking_lists.to_location_id', '=', 'to_warehouses.id')
                                 ->on('picking_lists.to_location_type', '=', DB::raw("'App\\\\Models\\\\Warehouse'"));
                        })
                        ->leftJoin('retailers as to_retailers', function($join) {
                            $join->on('picking_lists.to_location_id', '=', 'to_retailers.id')
                                 ->on('picking_lists.to_location_type', '=', DB::raw("'App\\\\Models\\\\Retailer'"));
                        })
                        ->where('picking_list_items.product_id', $product->id)
                        ->select(
                            'picking_lists.id',
                            'picking_lists.picking_date',
                            'picking_lists.status',
                            // Deliberately not picking_type - there is no such
                            // column, and asking for it made this whole query
                            // throw, so the section came back empty every time.
                            // The two ends are what the type is derived from;
                            // see PickingList::getPickingTypeAttribute().
                            'picking_lists.from_location_type',
                            'picking_lists.to_location_type',
                            'picking_list_items.quantity',
                            DB::raw('COALESCE(from_warehouses.name, from_retailers.name) as from_location_name'),
                            DB::raw('COALESCE(to_warehouses.name, to_retailers.name) as to_location_name')
                        )
                        ->orderBy('picking_lists.picking_date', 'desc')
                        ->get()
                        ->map(function ($picking) {
                            // Create fromLocation and toLocation objects for the view
                            $picking->fromLocation = $picking->from_location_name ? (object) [
                                'name' => $picking->from_location_name,
                            ] : null;
                            $picking->toLocation = $picking->to_location_name ? (object) [
                                'name' => $picking->to_location_name,
                            ] : null;
                            $picking->picking_number = 'PL-' . str_pad($picking->id, 6, '0', STR_PAD_LEFT);
                            // These rows are stdClass, not PickingList, so the
                            // model's accessor cannot apply. Derived the same
                            // way so both agree.
                            $picking->picking_type = ($picking->from_location_type && $picking->to_location_type)
                                ? \Illuminate\Support\Str::snake(class_basename($picking->from_location_type))
                                    .'_to_'
                                    .\Illuminate\Support\Str::snake(class_basename($picking->to_location_type))
                                : null;
                            return $picking;
                        });
                } catch (Exception $e) {
                    // Log error but continue
                    Log::error('Error getting picking lists', ['error' => $e->getMessage()]);
                }

                // Ensure pickingLists is always a collection
                if (!$pickingLists instanceof \Illuminate\Support\Collection) {
                    $pickingLists = collect();
                }

                // Calculate current stock totals
                $currentTotalStock = $stockBalances->sum('quantity');
                $currentAvailableStock = $stockBalances->sum('available_quantity');
                $currentReservedStock = $currentTotalStock - $currentAvailableStock;

                return [
                    'product' => $product,
                    'totalSupplied' => $totalSupplied,
                    'totalSold' => $totalSold,
                    'totalTransferred' => $totalTransferred,
                    'currentTotalStock' => $currentTotalStock,
                    'currentAvailableStock' => $currentAvailableStock,
                    'currentReservedStock' => $currentReservedStock,
                    'stockBalances' => $stockBalances,
                    'movements' => $movements,
                    'pickingLists' => $pickingLists,
                ];
            },
            'product transaction history',
            $product->id
        );
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'search' => [
                'type' => 'text',
                'label' => 'Search',
                'placeholder' => 'Search by name, SKU, or description'
            ],
            'category_id' => [
                'type' => 'select',
                'label' => 'Category',
                'placeholder' => 'Select category',
                'options' => $this->getCategoryOptions()
            ],
            'subcategory_id' => [
                'type' => 'select',
                'label' => 'Subcategory',
                'placeholder' => 'Select subcategory',
                'options' => []
            ],
            'price_min' => [
                'type' => 'number',
                'label' => 'Min Price',
                'placeholder' => 'Minimum price'
            ],
            'price_max' => [
                'type' => 'number',
                'label' => 'Max Price',
                'placeholder' => 'Maximum price'
            ],
            'stock_min' => [
                'type' => 'number',
                'label' => 'Min Stock',
                'placeholder' => 'Minimum stock level'
            ],
            'stock_max' => [
                'type' => 'number',
                'label' => 'Max Stock',
                'placeholder' => 'Maximum stock level'
            ]
        ];
    }

    /**
     * Get category options for filter dropdown
     */
    public function getCategoryOptions(): array
    {
        $categories = \App\Models\ProductCategory::getMainCategories();
        $options = ['' => 'All Categories'];

        foreach ($categories as $category) {
            $options[$category->id] = $category->name;
        }

        return $options;
    }

    /**
     * Get subcategory options for a given category
     */
    public function getSubcategoryOptions(int $categoryId): array
    {
        $subcategories = \App\Models\ProductCategory::getSubcategories($categoryId);
        $options = ['' => 'All Subcategories'];

        foreach ($subcategories as $subcategory) {
            $options[$subcategory->id] = $subcategory->name;
        }

        return $options;
    }

    /**
     * Get sort options for the view
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'sku' => 'SKU',
            'selling_price' => 'Price',
            'available_stocks' => 'Stock Level',
            'created_at' => 'Created Date',
        ];
    }

    /**
     * Get stock analysis for a product
     */
    public function stockAnalysis(Product $product): array
    {
        return $this->handleServiceOperation(
            function() use ($product) {
                // Get total supplied quantity (completed supplies)
                $totalSupplied = DB::table('supply_items')
                    ->join('supplies', 'supply_items.supply_id', '=', 'supplies.id')
                    ->where('supply_items.product_id', $product->id)
                    ->where('supplies.status', 'completed')
                    ->sum('supply_items.quantity');

                // Get total ordered quantity (completed orders)
                $totalOrdered = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('order_items.product_id', $product->id)
                    ->where('orders.status', 'completed')
                    ->sum('order_items.quantity');

                // Calculate current stock
                $currentStock = $totalSupplied - $totalOrdered;

                // Get pending supplies
                $pendingSupplies = DB::table('supply_items')
                    ->join('supplies', 'supply_items.supply_id', '=', 'supplies.id')
                    ->where('supply_items.product_id', $product->id)
                    ->whereIn('supplies.status', ['pending', 'confirmed'])
                    ->sum('supply_items.quantity');

                // Get pending orders
                $pendingOrders = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('order_items.product_id', $product->id)
                    ->whereIn('orders.status', ['pending', 'processing'])
                    ->sum('order_items.quantity');

                // Calculate projected stock
                $projectedStock = $currentStock + $pendingSupplies - $pendingOrders;

                // Get stock by location using product_stocks table
                $stockByLocation = DB::table('product_stocks')
                    ->leftJoin('stock_locations', function($join) {
                        $join->on('product_stocks.location_id', '=', 'stock_locations.id')
                             ->where('product_stocks.location_type', '=', DB::raw("'App\\\\Models\\\\StockLocation'"));
                    })
                    ->leftJoin('warehouses', function($join) {
                        $join->on('product_stocks.location_id', '=', 'warehouses.id')
                             ->where('product_stocks.location_type', '=', DB::raw("'App\\\\Models\\\\Warehouse'"));
                    })
                    ->leftJoin('retailers', function($join) {
                        $join->on('product_stocks.location_id', '=', 'retailers.id')
                             ->where('product_stocks.location_type', '=', DB::raw("'App\\\\Models\\\\Retailer'"));
                    })
                    ->leftJoin(DB::raw('(SELECT 
                        location_id, 
                        location_type, 
                        MAX(transaction_date) as last_movement_date 
                        FROM stock_transactions 
                        WHERE product_id = ' . $product->id . ' 
                        GROUP BY location_id, location_type) as last_movements'), function($join) {
                        $join->on('product_stocks.location_id', '=', 'last_movements.location_id')
                             ->on('product_stocks.location_type', '=', 'last_movements.location_type');
                    })
                    ->where('product_stocks.product_id', $product->id)
                    ->select(
                        'product_stocks.*',
                        DB::raw('COALESCE(stock_locations.name, warehouses.name, retailers.name) as location_name'),
                        DB::raw('COALESCE(stock_locations.type, 
                            CASE WHEN warehouses.id IS NOT NULL THEN "warehouse" 
                                 WHEN retailers.id IS NOT NULL THEN "retailer" 
                                 ELSE "unknown" END) as location_type'),
                        DB::raw('(product_stocks.quantity - COALESCE(product_stocks.reserved_quantity, 0)) as available_quantity'),
                        'last_movements.last_movement_date'
                    )
                    ->get();

                // Get supplies history
                $supplies = DB::table('supply_items')
                    ->join('supplies', 'supply_items.supply_id', '=', 'supplies.id')
                    ->join('vendors', 'supplies.vendor_id', '=', 'vendors.id')
                    ->where('supply_items.product_id', $product->id)
                    ->select(
                        'supply_items.supply_id',
                        'supplies.supply_date',
                        'vendors.name as vendor_name',
                        'supply_items.quantity',
                        'supply_items.unit_cost',
                        'supplies.status'
                    )
                    ->orderBy('supplies.supply_date', 'desc')
                    ->get();

                // Get orders history
                $orders = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('customers', 'orders.customer_id', '=', 'customers.id')
                    ->where('order_items.product_id', $product->id)
                    ->select(
                        'order_items.order_id',
                        'orders.order_date',
                        'customers.name as customer_name',
                        'order_items.quantity',
                        'order_items.unit_price',
                        'orders.status'
                    )
                    ->orderBy('orders.order_date', 'desc')
                    ->get();

                // Get return statistics
                $returnStats = DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->whereIn('transaction_type', [
                        \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN,
                        \App\Models\StockTransaction::TYPE_VENDOR_RETURN,
                        \App\Models\StockTransaction::TYPE_RETAILER_RETURN
                    ])
                    ->selectRaw('
                        transaction_type,
                        direction,
                        status,
                        SUM(CASE WHEN direction = "inbound" THEN quantity ELSE 0 END) as inbound_quantity,
                        SUM(CASE WHEN direction = "outbound" THEN quantity ELSE 0 END) as outbound_quantity,
                        COUNT(*) as transaction_count
                    ')
                    ->groupBy('transaction_type', 'direction', 'status')
                    ->get();

                // Calculate return totals
                $totalCustomerReturns = $returnStats->where('transaction_type', \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN)->sum('inbound_quantity');
                $totalVendorReturns = $returnStats->where('transaction_type', \App\Models\StockTransaction::TYPE_VENDOR_RETURN)->sum('outbound_quantity');
                $totalRetailerReturns = $returnStats->where('transaction_type', \App\Models\StockTransaction::TYPE_RETAILER_RETURN)->sum('inbound_quantity');
                $pendingReturns = $returnStats->where('status', 'pending')->sum('inbound_quantity') + $returnStats->where('status', 'pending')->sum('outbound_quantity');

                // Get return history
                $returns = DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->whereIn('transaction_type', [
                        \App\Models\StockTransaction::TYPE_CUSTOMER_RETURN,
                        \App\Models\StockTransaction::TYPE_VENDOR_RETURN,
                        \App\Models\StockTransaction::TYPE_RETAILER_RETURN
                    ])
                    ->select(
                        'id',
                        'transaction_type',
                        'direction',
                        'quantity',
                        'transaction_date',
                        'status',
                        'notes'
                    )
                    ->orderBy('transaction_date', 'desc')
                    ->get();

                return [
                    'product' => $product,
                    'current_stock' => $currentStock,
                    'total_supplied' => $totalSupplied,
                    'total_ordered' => $totalOrdered,
                    'projected_stock' => $projectedStock,
                    'stock_by_location' => $stockByLocation,
                    'pending_supplies' => $pendingSupplies,
                    'pending_orders' => $pendingOrders,
                    'supplies' => $supplies,
                    'orders' => $orders,
                    'total_customer_returns' => $totalCustomerReturns,
                    'total_vendor_returns' => $totalVendorReturns,
                    'total_retailer_returns' => $totalRetailerReturns,
                    'pending_returns' => $pendingReturns,
                    'returns' => $returns,
                ];
            },
            'product stock analysis',
            $product->id
        );
    }
}
