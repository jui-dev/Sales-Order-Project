<?php

namespace App\Http\Controllers;

use App\Services\TransactionFlowService;
use Illuminate\View\View;

class TransactionFlowController extends Controller
{
    public function __construct(private readonly TransactionFlowService $transactionFlowService)
    {
    }

    /**
     * Display the transaction flow dashboard
     */
    public function index(): View
    {
        $stockSummary = $this->transactionFlowService->getStockSummary();
        $recentMovements = $this->transactionFlowService->getRecentMovements();
        $warehouses = $this->transactionFlowService->getWarehouses();
        $retailers = $this->transactionFlowService->getRetailers();

        return view('picking.transaction-flow', compact(
            'stockSummary', 
            'recentMovements', 
            'warehouses', 
            'retailers'
        ));
    }
} 