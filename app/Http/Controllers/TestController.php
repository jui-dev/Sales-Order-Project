<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Order;

class TestController extends Controller
{
    public function testOrders(): JsonResponse
    {
        try {
            \Log::info('TestController: Starting to test orders...');
            
            // Test 1: Basic query
            $count = Order::count();
            \Log::info("TestController: Order count: {$count}");
            
            // Test 2: Simple query without relationships
            $orders = Order::latest()->limit(5)->get();
            \Log::info("TestController: Simple query successful. Count: {$orders->count()}");
            
            // Test 3: Query with customer relationship
            $ordersWithCustomer = Order::with('customer')->latest()->limit(5)->get();
            \Log::info("TestController: Query with customer successful. Count: {$ordersWithCustomer->count()}");
            
            // Test 4: Query with all relationships
            $ordersWithAll = Order::with(['customer', 'items', 'invoice'])->latest()->limit(5)->get();
            \Log::info("TestController: Query with all relationships successful. Count: {$ordersWithAll->count()}");
            
            return response()->json([
                'success' => true,
                'message' => 'All tests passed',
                'data' => [
                    'total_count' => $count,
                    'simple_query_count' => $orders->count(),
                    'with_customer_count' => $ordersWithCustomer->count(),
                    'with_all_count' => $ordersWithAll->count(),
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error("TestController: Error occurred: " . $e->getMessage());
            \Log::error("TestController: Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
} 