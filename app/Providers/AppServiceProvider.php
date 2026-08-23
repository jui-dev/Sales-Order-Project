<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\Supply;
use App\Models\Order;
use App\Models\StockTransaction;
use App\Observers\SupplyObserver;
use App\Observers\OrderObserver;
use App\Observers\StockTransactionObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Product;
use App\Observers\ProductObserver;
// Disabled: use App\Models\WarehouseStock;
// Disabled: use App\Observers\WarehouseStockObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ------------------------------------------------------------------
        // Login rate limiting
        // ------------------------------------------------------------------
        //
        // Auth::attempt was reachable without limit, so a password could be
        // guessed as fast as the server would answer. Keyed on the submitted
        // email as well as the IP: keying on the IP alone lets one attacker
        // behind a shared address lock out everyone else working there.
        //
        // Registered here rather than in RouteServiceProvider, which is not
        // listed in bootstrap/providers.php and so never boots.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });

        // ------------------------------------------------------------------
        // Ensure Chart of Accounts exists
        // ------------------------------------------------------------------
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('accounts')) {
                // Run seeder unconditionally – it uses firstOrCreate / updateOrInsert so
                // existing rows remain untouched while missing ones are added.
                app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
            }
        } catch (\Throwable $e) {
            // During migrations or when database is inaccessible we silently skip.
            // Seeding will be attempted again on next boot.
        }

        // Register model observers only if classes exist (prevents migration issues during scaffolding)
        if (class_exists(Supply::class) && class_exists(SupplyObserver::class)) {
            Supply::observe(SupplyObserver::class);
        }

        if (class_exists(Order::class) && class_exists(OrderObserver::class)) {
            Order::observe(OrderObserver::class);
        }

        if (class_exists(StockTransaction::class) && class_exists(StockTransactionObserver::class)) {
            StockTransaction::observe(StockTransactionObserver::class);
        }

        // Product pricing is derived, not entered: ProductObserver recalculates
        // selling_price from purchase_price + markup. purchase_price itself is
        // written only when goods are received (GrnService::applyReceivedCost).
        if (class_exists(Product::class) && class_exists(ProductObserver::class)) {
            Product::observe(ProductObserver::class);
        }
        if (class_exists(\App\Models\ProductStock::class) && class_exists(\App\Observers\ProductStockObserver::class)) {
            \App\Models\ProductStock::observe(\App\Observers\ProductStockObserver::class);
        }
        // Disabled: WarehouseStock::observe(WarehouseStockObserver::class);

        if (class_exists(\App\Models\Grn::class) && class_exists(\App\Observers\GrnObserver::class)) {
            \App\Models\Grn::observe(\App\Observers\GrnObserver::class);
        }

        if (class_exists(\App\Models\Invoice::class) && class_exists(\App\Observers\InvoiceObserver::class)) {
            \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        }

        // ReturnRecord observer removed - model no longer exists

        if (class_exists(\App\Models\StockTransfer::class) && class_exists(\App\Observers\StockTransferObserver::class)) {
            \App\Models\StockTransfer::observe(\App\Observers\StockTransferObserver::class);
        }

        // Observe picking lists completion to finalise stock deduction
        if (class_exists(\App\Models\PickingList::class) && class_exists(\App\Observers\PickingListObserver::class)) {
            \App\Models\PickingList::observe(\App\Observers\PickingListObserver::class);
        }

        // Provide default empty collections / counters to all Blade views so that
        // missing backend data does not trigger "Undefined variable" errors
        // in this UI-only prototype. This keeps the templates functional until
        // real controllers and database queries are implemented.
        $placeholders = [
            // Collections
            'warehouses'        => collect(),
            'vendors'           => collect(),
            'customers'         => collect(),
            'products'          => collect(),
            'orders'            => collect(),
            'supplies'          => collect(),
            'returns'           => new LengthAwarePaginator([], 0, 15, 1, ['path' => url()->current()]),
            'retailers'         => collect(),
            'transfers'         => collect(),
            'pickingLists'      => collect(),
            'recentSupplies'    => collect(),
            'recentOrders'      => collect(),
            'stockTransactions' => new LengthAwarePaginator([], 0, 15, 1, ['path' => url()->current()]),

            // Counters / aggregates
            'totalVendors'      => 0,
            'totalWarehouses'   => 0,
            'totalRetailers'    => 0,
            'totalTransfers'    => 0,

            // Generic statistics placeholder for pages expecting an array structure
            'statistics' => [
                'total_picking_lists'      => 0,
                'completed_picking_lists'  => 0,
                'pending_picking_lists'    => 0,
                'in_progress_picking_lists'=> 0,
            ],

            // Defaults for product transaction history view
            'product'            => new class {
                public int $id = 0;
                public string $name = 'Placeholder';
                public function __toString(): string
                {
                    return (string) $this->id;
                }
            },
            'totalSupplied'      => 0,
            'totalSold'          => 0,
            'totalTransferred'   => 0,
            'currentTotalStock'  => 0,
            'currentAvailableStock' => 0,
            'currentReservedStock'  => 0,
            'stockBalances'      => collect(),
            'movements'          => collect(),

            // stock locations list placeholder
            'stockLocations'     => collect(),
            'locations'          => collect(),

            // Default date range for reports
            'startDate'         => date('Y-m-01'),
            'endDate'           => date('Y-m-d'),

            // Reports placeholders
            'dailyProfits'      => collect(),
            'dailyTotals'       => collect(),
            // Keys mirror ReportService::getBlankSummary(). A page that renders
            // without its controller shows zeros rather than an undefined-index
            // error, which is the only reason these are here.
            'summary'           => [
                'gross_revenue'      => 0,
                'total_returns'      => 0,
                'total_discounts'    => 0,
                'total_revenue'      => 0,
                'total_cost'         => 0,
                'total_profit'       => 0,
                'profit_margin'      => 0,
                'average_margin'     => 0,
                'products_count'     => 0,
                'days_count'         => 0,
                'unattributed'       => 0,
                'warehouse_revenue'  => 0,
                'warehouse_profit'   => 0,
                'warehouse_products' => 0,
                'warehouse_margin'   => 0,
                'retailer_revenue'   => 0,
                'retailer_profit'    => 0,
                'retailer_products'  => 0,
                'retailer_margin'    => 0,
            ],
            'basis'             => [
                'posted_count'   => 0,
                'pending_count'  => 0,
                'pending_total'  => 0,
                'draft_count'    => 0,
                'approved_count' => 0,
                'is_complete'    => true,
            ],
            'stockData' => [
                'product'           => null,
                'current_stock'     => 0,
                'total_supplied'    => 0,
                'total_ordered'     => 0,
                'projected_stock'   => 0,
                'pending_supplies'  => 0,
                'pending_orders'    => 0,
                'stock_by_location' => collect(),
                'supplies'          => collect(),
                'orders'            => collect(),
            ],
            'stockSummary' => [
                'total_products'     => 0,
                'total_stock_value'  => 0,
                'pending_movements'  => 0,
                'active_pickings'    => 0,
            ],
            'recentMovements' => collect(),
        ];

        View::share($placeholders);

        // Back-fill only the placeholders above. A controller that passes null
        // means it - "there is no purchase order behind this supply" - and
        // converting every null handed views a Collection they then treated as
        // an object. An empty Collection is truthy, so guards written as
        // @if($thing) took the wrong branch and read properties off it.
        \Illuminate\Support\Facades\View::composer('*', function ($view) use ($placeholders) {
            foreach ($view->getData() as $key => $value) {
                if ($value === null && array_key_exists($key, $placeholders)) {
                    $view->with($key, $placeholders[$key]);
                }
            }
        });
    }
}
