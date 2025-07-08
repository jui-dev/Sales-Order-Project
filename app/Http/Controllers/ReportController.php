<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Models\Retailer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    /**
     * Show Daily Profit Report page with calculated data.
     */
    public function dailyProfit(Request $request)
    {
        // Validate date input – allow empty which falls back to defaults
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Determine date range – default to current month
        $startDate = Arr::get($validated, 'start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = Arr::get($validated, 'end_date', Carbon::now()->toDateString());

        // Fetch all order items within the date range whose parent orders are not cancelled
        $orderItems = OrderItem::with(['order', 'product', 'location'])
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereDate('order_date', '>=', $startDate)
                  ->whereDate('order_date', '<=', $endDate)
                  // Include every status except explicitly cancelled so that in-flight
                  // orders still appear in the profitability dashboard.
                  ->where('status', '!=', 'cancelled');
            })
            ->get();

        // Early exit – no data
        if ($orderItems->isEmpty()) {
            return view('reports.daily-profit', [
                'startDate'     => $startDate,
                'endDate'       => $endDate,
                'dailyProfits'  => collect(),
                'dailyTotals'   => collect(),
                'summary'       => $this->blankSummary(),
            ]);
        }

        // Build per-item profit records
        $dailyProfits = $orderItems->map(function (OrderItem $item) {
            $product        = $item->product;
            $locationModel  = $item->location;

            // Normalise location type to friendly slug
            $locationType = match ($item->location_type) {
                \App\Models\Warehouse::class => 'warehouse',
                \App\Models\Retailer::class  => 'retailer',
                default                        => 'other',
            };

            // Guard against missing location records
            $locationName = $locationModel?->name ?? 'Unknown';

            $revenue = (float) $item->unit_price * (int) $item->quantity;
            $cost    = (float) ($product->purchase_price ?? 0) * (int) $item->quantity;
            $profit  = $revenue - $cost;

            return [
                'order_date'    => $item->order->order_date->toDateString(),
                'product_id'    => $item->product_id,
                'product_name'  => $product->name ?? 'Product',
                'location_type' => $locationType,
                'location_name' => $locationName,
                'quantity_sold' => (int) $item->quantity,
                'revenue'       => round($revenue, 2),
                'cost'          => round($cost, 2),
                'profit'        => round($profit, 2),
            ];
        });

        // Aggregate daily totals
        $dailyTotals = $dailyProfits->groupBy('order_date')->map(function (Collection $items) {
            return [
                'date'             => $items->first()['order_date'],
                'products_count'   => $items->sum('quantity_sold'),
                'total_revenue'    => $items->sum('revenue'),
                'total_cost'       => $items->sum('cost'),
                'total_profit'     => $items->sum('profit'),
                'warehouse_profit' => $items->where('location_type', 'warehouse')->sum('profit'),
                'retailer_profit'  => $items->where('location_type', 'retailer')->sum('profit'),
            ];
        })->sortBy('date')->values();

        // Overall summary
        $summary = $this->buildSummary($dailyProfits);

        return view('reports.daily-profit', compact('startDate', 'endDate', 'dailyProfits', 'dailyTotals', 'summary'));
    }

    private function blankSummary(): array
    {
        return [
            'total_products_sold'   => 0,
            'total_revenue'         => 0,
            'total_profit'          => 0,
            'average_margin'        => 0,
            'warehouse_products_sold' => 0,
            'warehouse_revenue'     => 0,
            'warehouse_profit'      => 0,
            'warehouse_margin'      => 0,
            'retailer_products_sold' => 0,
            'retailer_revenue'      => 0,
            'retailer_profit'       => 0,
            'retailer_margin'       => 0,
        ];
    }

    private function buildSummary(Collection $items): array
    {
        if ($items->isEmpty()) {
            return $this->blankSummary();
        }

        $totalRevenue = $items->sum('revenue');
        $totalProfit  = $items->sum('profit');
        $warehouseItems = $items->where('location_type', 'warehouse');
        $retailerItems  = $items->where('location_type', 'retailer');

        $warehouseRevenue = $warehouseItems->sum('revenue');
        $warehouseProfit  = $warehouseItems->sum('profit');
        $retailerRevenue  = $retailerItems->sum('revenue');
        $retailerProfit   = $retailerItems->sum('profit');

        return [
            'total_products_sold'     => $items->sum('quantity_sold'),
            'total_revenue'           => $totalRevenue,
            'total_profit'            => $totalProfit,
            'average_margin'          => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0,
            'warehouse_products_sold' => $warehouseItems->sum('quantity_sold'),
            'warehouse_revenue'       => $warehouseRevenue,
            'warehouse_profit'        => $warehouseProfit,
            'warehouse_margin'        => $warehouseRevenue > 0 ? round(($warehouseProfit / $warehouseRevenue) * 100, 1) : 0,
            'retailer_products_sold'  => $retailerItems->sum('quantity_sold'),
            'retailer_revenue'        => $retailerRevenue,
            'retailer_profit'         => $retailerProfit,
            'retailer_margin'         => $retailerRevenue > 0 ? round(($retailerProfit / $retailerRevenue) * 100, 1) : 0,
        ];
    }
} 