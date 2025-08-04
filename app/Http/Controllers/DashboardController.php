<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\Customer;
use App\Models\Vendor;

use App\Models\Payment;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        $today = Carbon::today();
        
        $stats = [
            'totalSalesToday' => Invoice::whereDate('created_at', $today)->sum('total'),
            'returnsToday' => StockTransaction::whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
                ->whereDate('created_at', $today)->count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'currentStockValue' => Product::sum(DB::raw('available_stocks * selling_price')),
            'outstandingPayments' => Invoice::where('payment_status', 'unpaid')->sum('total'),
            'totalProducts' => Product::count(),
            'totalCustomers' => Customer::count(),
            'totalVendors' => Vendor::count(),
        ];
        
        return response()->json($stats);
    }

    /**
     * Get sales trend data for the specified period
     */
    public function getSalesTrend(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($days);
        
        $salesData = Invoice::selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $labels = $salesData->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values();
        $data = $salesData->pluck('total')->values();
        
        return response()->json([
            'labels' => $labels,
            'data' => $data
        ]);
    }

    /**
     * Get top selling products for the specified period
     */
    public function getTopProducts(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($days);
        
        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->selectRaw('products.name, SUM(invoice_items.quantity) as total_quantity, SUM(invoice_items.total) as total_sales')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();
        
        return response()->json($topProducts);
    }

    /**
     * Get stock movement data for the specified period
     */
    public function getStockMovement(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($days);
        
        $stockInflow = StockTransaction::where('direction', 'in')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $stockOutflow = StockTransaction::where('direction', 'out')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $labels = $stockInflow->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values();
        $inflowData = $stockInflow->pluck('total')->values();
        $outflowData = $stockOutflow->pluck('total')->values();
        
        return response()->json([
            'labels' => $labels,
            'inflow' => $inflowData,
            'outflow' => $outflowData
        ]);
    }

    /**
     * Get returns breakdown data for the specified period
     */
    public function getReturnsBreakdown(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($days);
        
        $customerReturns = StockTransaction::where('transaction_type', 'customer_return')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $vendorReturns = StockTransaction::where('transaction_type', 'vendor_return')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $labels = $customerReturns->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values();
        $customerData = $customerReturns->pluck('count')->values();
        $vendorData = $vendorReturns->pluck('count')->values();
        
        return response()->json([
            'labels' => $labels,
            'customerReturns' => $customerData,
            'vendorReturns' => $vendorData
        ]);
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(): JsonResponse
    {
        $lowStockProducts = Product::where('available_stocks', '<=', 10)
            ->orderBy('available_stocks')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'available_stocks', 'selling_price']);
        
        return response()->json($lowStockProducts);
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(): JsonResponse
    {
        $recentOrders = Order::with('customer')
            ->latest()
            ->limit(5)
            ->get(['id', 'formatted_id', 'customer_id', 'created_at']);
        
        $recentReturns = StockTransaction::whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
            ->with(['product:id,name', 'location:id,name'])
            ->latest()
            ->limit(5)
            ->get(['id', 'transaction_type', 'product_id', 'location_id', 'created_at']);
        
        return response()->json([
            'orders' => $recentOrders,
            'returns' => $recentReturns
        ]);
    }
} 