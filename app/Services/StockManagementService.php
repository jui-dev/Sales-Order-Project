<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLocation;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Traits\HasErrorHandling;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockManagementService
{
    use HasErrorHandling;

    /**
     * Get filtered stock transactions with pagination
     */
    public function getFilteredStockTransactions(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = StockTransaction::with(['product', 'location'])->latest('id');

                // Apply dynamic filters
                if (!empty($filters['product_search'])) {
                    $query->whereHas('product', function ($q) use ($filters) {
                        $q->where('name', 'like', '%' . $filters['product_search'] . '%');
                    });
                }

                if (!empty($filters['location_type'])) {
                    $query->where('location_type', $filters['location_type'] === 'warehouse' ? Warehouse::class : Retailer::class);
                }

                if (!empty($filters['location_id'])) {
                    $query->where('location_id', $filters['location_id']);
                }

                if (!empty($filters['transaction_type'])) {
                    $query->where('transaction_type', $filters['transaction_type']);
                }

                if (!empty($filters['status']) && \Schema::hasColumn('stock_transactions', 'status')) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('transaction_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $query->whereDate('transaction_date', '<=', $filters['date_to']);
                }

                $stockTransactions = $query->paginate($perPage);

                // Enrich each transaction with additional data
                $stockTransactions->getCollection()->transform(function (StockTransaction $txn) {
                    return $this->enrichTransaction($txn);
                });

                return $stockTransactions;
            },
            'stock_transactions',
            $perPage,
            $filters
        );
    }

    /**
     * Get product transaction history
     */
    public function getProductTransactionHistory(Product $product, array $filters = []): array
    {
        return $this->handleServiceOperation(
            function() use ($product, $filters) {
                $query = StockTransaction::with(['location', 'reference'])
                    ->where('product_id', $product->id)
                    ->latest('transaction_date');

                // Apply filters
                if (!empty($filters['location_type'])) {
                    $query->where('location_type', $filters['location_type'] === 'warehouse' ? Warehouse::class : Retailer::class);
                }

                if (!empty($filters['transaction_type'])) {
                    $query->where('transaction_type', $filters['transaction_type']);
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('transaction_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $query->whereDate('transaction_date', '<=', $filters['date_to']);
                }

                $transactions = $query->get();

                // Enrich transactions
                $transactions->transform(function (StockTransaction $txn) {
                    return $this->enrichTransaction($txn);
                });

                // Calculate summary statistics
                $summary = $this->calculateProductSummary($transactions);

                return [
                    'transactions' => $transactions,
                    'summary' => $summary,
                ];
            },
            'product_transaction_history'
        );
    }

    /**
     * Export transactions to CSV
     */
    public function exportTransactionsToCsv(Collection $transactions): string
    {
        $csv = "Transaction ID,Date,Product,Location,Type,Direction,Quantity,Unit Cost,Total Cost,Status,Notes\n";

        foreach ($transactions as $txn) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $txn->formatted_id,
                $txn->transaction_date->format('Y-m-d'),
                $txn->product?->name ?? 'Unknown',
                $txn->location?->name ?? 'Unknown',
                $txn->transaction_type,
                $txn->direction,
                $txn->quantity,
                number_format($txn->unit_cost, 2),
                number_format($txn->total_cost, 2),
                $txn->status ?? 'completed',
                str_replace('"', '""', $txn->notes ?? '')
            );
        }

        return $csv;
    }

    /**
     * Get filter options for stock transactions
     */
    public function getFilterOptions(): array
    {
        return [
            'transaction_types' => [
                'supply' => 'Supply',
                'order' => 'Order',
                'transfer' => 'Transfer',
                'return' => 'Return',
                'adjustment' => 'Adjustment',
            ],
            'location_types' => [
                'warehouse' => 'Warehouse',
                'retailer' => 'Retailer',
            ],
            'statuses' => [
                'pending' => 'Pending',
                'approved' => 'Approved',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'locations' => [
                'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id')->toArray(),
                'retailers' => Retailer::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
        ];
    }

    /**
     * Get sort options for stock transactions
     */
    public function getSortOptions(): array
    {
        return [
            'transaction_date' => 'Transaction Date',
            'product_name' => 'Product Name',
            'transaction_type' => 'Transaction Type',
            'quantity' => 'Quantity',
            'location_name' => 'Location',
            'status' => 'Status',
        ];
    }

    /**
     * Enrich a stock transaction with additional data
     */
    private function enrichTransaction(StockTransaction $txn): StockTransaction
    {
        // Source / Destination Locations
        if ($txn->direction === 'out') {
            $txn->sourceLocation = $txn->location; // where stock left
            $txn->destinationLocation = null;
        } else {
            $txn->sourceLocation = null;
            $txn->destinationLocation = $txn->location; // where stock arrived
        }

        // Unit & Total Costs - a transaction that never captured its own cost
        // is valued at what the goods cost on the day it happened, not at
        // today's figure, which would drift with every later delivery.
        if (empty($txn->unit_cost) || $txn->unit_cost === 0.0) {
            $txn->unit_cost = $txn->product
                ? app(\App\Services\Pricing\ProductCostService::class)->costAtOrLegacy(
                    $txn->product,
                    $txn->transaction_date ? \Illuminate\Support\Carbon::parse($txn->transaction_date) : now(),
                )
                : 0.0;
        }
        $txn->total_cost = $txn->unit_cost * abs($txn->quantity);

        // Notes & Status – use reference model if available (but preserve return reasons)
        if (empty($txn->notes) && $txn->reference_type && $txn->reference_id && !$txn->isReturn()) {
            try {
                $ref = app($txn->reference_type)::find($txn->reference_id);
                $txn->notes = $ref->notes ?? null;
                $txn->status = $ref->status ?? ($txn->status ?? 'completed');
            } catch (\Throwable $e) {
                $txn->status ??= 'completed';
            }
        }

        return $txn;
    }

    /**
     * Calculate summary statistics for product transactions
     */
    private function calculateProductSummary(Collection $transactions): array
    {
        $totalInbound = $transactions->where('direction', 'in')->sum('quantity');
        $totalOutbound = $transactions->where('direction', 'out')->sum('quantity');
        $netMovement = $totalInbound - $totalOutbound;

        $totalInboundCost = $transactions->where('direction', 'in')->sum('total_cost');
        $totalOutboundCost = $transactions->where('direction', 'out')->sum('total_cost');

        return [
            'total_inbound' => $totalInbound,
            'total_outbound' => $totalOutbound,
            'net_movement' => $netMovement,
            'total_inbound_cost' => $totalInboundCost,
            'total_outbound_cost' => $totalOutboundCost,
            'transaction_count' => $transactions->count(),
        ];
    }
} 