<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLocation;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Services\StockManagementService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class StockManagementController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly StockManagementService $stockManagementService)
    {
    }

    /**
     * Display the master list of stock transactions with optional filters.
     */
    public function index(Request $request): View
    {
        $filters = [
            'product_search' => $request->product_search,
            'location_type' => $request->location_type,
            'location_id' => $request->location_id,
            'transaction_type' => $request->transaction_type,
            'status' => $request->status,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $stockTransactions = $this->stockManagementService->getFilteredStockTransactions($filters, 50);

        // Get filter options for the view
        $locationTypes = [
            'warehouse' => 'Warehouse',
            'retailer' => 'Retailer',
        ];

        $transactionTypes = [
            'supply' => 'Supply',
            'order' => 'Order',
            'stock_transfer' => 'Stock Transfer',
            'customer_return' => 'Customer Return',
            'vendor_return' => 'Vendor Return',
            'retailer_return' => 'Retailer Return',
        ];

        $locations = collect();
        if ($request->location_type === 'warehouse') {
            $locations = Warehouse::orderBy('name')->get();
        } elseif ($request->location_type === 'retailer') {
            $locations = Retailer::orderBy('name')->get();
        }

        return view('stock-management.index', compact(
            'stockTransactions',
            'locationTypes',
            'transactionTypes',
            'locations',
            'filters'
        ));
    }

    /**
     * API endpoint to get all stock transactions
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'product_search' => $request->get('product_search'),
                    'location_type' => $request->get('location_type'),
                    'location_id' => $request->get('location_id'),
                    'transaction_type' => $request->get('transaction_type'),
                    'status' => $request->get('status'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                ];

                $perPage = $request->get('per_page', 50);
                return $this->stockManagementService->getFilteredStockTransactions($filters, $perPage);
            },
            'stock transactions',
            'Stock transactions retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific stock transaction
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                // For now, we'll use the existing getFilteredStockTransactions method
                // and find the specific transaction. In a real implementation, you might
                // want to add a specific method to the service for this.
                $transactions = $this->stockManagementService->getFilteredStockTransactions([], 1);
                $transaction = StockTransaction::with(['product', 'location', 'reference'])->find($id);
                
                if (!$transaction) {
                    throw new DataNotFoundException('stock transaction', $id);
                }
                
                return $transaction;
            },
            'stock transaction',
            'Stock transaction retrieved successfully'
        );
    }

    /**
     * Show product transaction history
     */
    public function productHistory(Product $product, Request $request): View
    {
        $filters = [
            'location_type' => $request->location_type,
            'transaction_type' => $request->transaction_type,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $result = $this->stockManagementService->getProductTransactionHistory($product, $filters);

        $locationTypes = [
            'warehouse' => 'Warehouse',
            'retailer' => 'Retailer',
        ];

        $transactionTypes = [
            'supply' => 'Supply',
            'order' => 'Order',
            'stock_transfer' => 'Stock Transfer',
            'customer_return' => 'Customer Return',
            'vendor_return' => 'Vendor Return',
            'retailer_return' => 'Retailer Return',
        ];

        return view('stock-management.product-history', compact(
            'product',
            'result',
            'locationTypes',
            'transactionTypes'
        ));
    }

    /**
     * Export stock transactions to CSV
     */
    public function export(Request $request): Response
    {
        $filters = [
            'product_search' => $request->product_search,
            'location_type' => $request->location_type,
            'location_id' => $request->location_id,
            'transaction_type' => $request->transaction_type,
            'status' => $request->status,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $stockTransactions = $this->stockManagementService->getFilteredStockTransactions($filters, 1000);
        $csv = $this->stockManagementService->exportTransactionsToCsv($stockTransactions->getCollection());

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="stock_transactions.csv"');
    }
} 