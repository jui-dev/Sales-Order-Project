<?php

namespace App\Http\Controllers;

use App\Models\StockTransaction;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Models\StockTransfer;
use App\Services\ReturnService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ReturnController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly ReturnService $returnService)
    {
    }

    /**
     * Display the returns index page with unified search
     */
    public function index(Request $request): View
    {
        $filters = [
            'type' => $request->get('type'),
            'location_id' => $request->get('location_id'),
            'product_id' => $request->get('product_id'),
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $perPage = $request->get('per_page', 20);
        $returns = $this->returnService->getAllReturns($filters, $perPage);
        $statistics = $this->returnService->getReturnStatistics();
        $filterOptions = $this->returnService->getFilterOptions();
        $sortOptions = $this->returnService->getSortOptions();
        $pageTitle = $this->returnService->getPageTitle($request->get('type'));
        $pageSubtitle = $this->returnService->getPageSubtitle($request->get('type'));

        return view('returns.index', compact('returns', 'statistics', 'filterOptions', 'sortOptions', 'pageTitle', 'pageSubtitle'));
    }

    /**
     * API endpoint to get all returns
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'type' => $request->get('type'),
                    'location_id' => $request->get('location_id'),
                    'product_id' => $request->get('product_id'),
                    'status' => $request->get('status'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                ];

                $perPage = $request->get('per_page', 20);
                return $this->returnService->getAllReturns($filters, $perPage);
            },
            'returns',
            'Returns retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific return
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                $return = StockTransaction::with(['product', 'location', 'reference'])->find($id);
                
                if (!$return || !$return->isReturn()) {
                    throw new DataNotFoundException('return', $id);
                }
                
                return $return;
            },
            'return',
            'Return retrieved successfully'
        );
    }

    /**
     * Show the return type selection page
     */
    public function create(): View
    {
        $customers = Customer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $retailers = Retailer::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('returns.create', compact('customers', 'vendors', 'retailers', 'warehouses'));
    }

    /**
     * Show the return creation form with pre-filled data
     */
    public function createWithData(Request $request): View
    {
        $request->validate([
            'return_type' => 'required|in:customer_return,vendor_return,retailer_return',
            'reference_id' => 'required|integer',
            'product_id' => 'required|integer',
        ]);

        $returnType = $request->return_type;
        $referenceId = $request->reference_id;
        $productId = $request->product_id;

        $customers = Customer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $retailers = Retailer::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('returns.create-with-data', compact(
            'returnType', 'referenceId', 'productId', 
            'customers', 'vendors', 'retailers', 'warehouses'
        ));
    }

    /**
     * Store a new return
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'return_type' => 'required|in:customer_return,vendor_return,retailer_return',
            'reference_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'return_reason' => 'required|string|max:500',
            'return_location_type' => 'nullable|in:warehouse,retailer,vendor',
            'return_location_id' => 'required|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Additional validation for retailer returns - ensure stock transfer is completed
        if ($request->return_type === 'retailer_return') {
            $stockTransfer = StockTransfer::find($request->reference_id);
            if (!$stockTransfer) {
                return back()->withInput()->with('error', 'Stock transfer not found.');
            }
            
            if ($stockTransfer->status !== 'completed') {
                return back()->withInput()->with('error', 'Only completed stock transfers can be used for retailer returns. Current status: ' . ucfirst($stockTransfer->status));
            }
        }

        try {
            // Log the request data for debugging
            \Log::info('Return creation request data:', [
                'return_type' => $request->return_type,
                'reference_id' => $request->reference_id,
                'return_reason' => $request->return_reason,
                'return_location_type' => $request->return_location_type,
                'return_location_id' => $request->return_location_id,
                'items_count' => count($request->items)
            ]);
            
            // Process each item in the items array
            $returns = [];
            foreach ($request->items as $item) {
                $data = array_merge($request->all(), $item);

                // Enforce the quantity rule here, not just in the form's JS -
                // a request posted directly would otherwise bypass it entirely.
                $this->returnService->assertReturnQuantityIsValid(
                    $request->return_type,
                    (int) $request->reference_id,
                    (int) $item['product_id'],
                    (int) $item['quantity']
                );

                // Map reference_id to the appropriate field based on return type
                switch ($request->return_type) {
                    case 'customer_return':
                        $data['invoice_id'] = $request->reference_id;
                        // Customer returns use return_location_type and return_location_id
                        break;
                    case 'vendor_return':
                        $data['supplier_bill_id'] = $request->reference_id;
                        // Vendor returns use vendor_id (supplier as destination)
                        $data['vendor_id'] = $request->return_location_id;
                        break;
                    case 'retailer_return':
                        $data['stock_transfer_id'] = $request->reference_id;
                        // Retailer returns use warehouse_id
                        $data['warehouse_id'] = $request->return_location_id;
                        break;
                }
                
                $return = match($request->return_type) {
                    'customer_return' => $this->returnService->createCustomerReturn($data),
                    'vendor_return' => $this->returnService->createVendorReturn($data),
                    'retailer_return' => $this->returnService->createRetailerReturn($data),
                };
                $returns[] = $return;
            }

            // Redirect to the first return or index
            if (empty($returns)) {
                return back()->withInput()->with('error', 'No returns were created.');
            }
            
            $firstReturn = $returns[0];
            
            // Special handling for retailer returns
            if ($request->return_type === 'retailer_return') {
                return redirect()->route('returns.show', $firstReturn->id)
                    ->with('success', 'Retailer return created successfully with "issued" status. You can now approve the stock adjustment.');
            }
            
            return redirect()->route('returns.show', $firstReturn->id)
                ->with('success', 'Return(s) created successfully.');
        } catch (\Exception $e) {
            \Log::error('Return creation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified return
     */
    public function show(StockTransaction $return): View
    {
        // Ensure this is a return transaction
        if (!$return->isReturn()) {
            abort(404, 'Return not found');
        }

        $return->load([
            'product',
            'location',
            'reference'
        ]);

        return view('returns.show', compact('return'));
    }

    /**
     * Show the form for editing the specified return
     */
    public function edit(StockTransaction $return): View|RedirectResponse
    {
        // Ensure this is a return transaction
        if (!$return->isReturn()) {
            abort(404, 'Return not found');
        }

        // Only allow editing of pending returns
        if (!$return->isPending()) {
            return redirect()->route('returns.show', $return->id)
                ->with('error', 'Only pending returns can be edited.');
        }

        $return->load(['product', 'location', 'reference']);

        $customers = Customer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $retailers = Retailer::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('returns.edit', compact('return', 'customers', 'vendors', 'retailers', 'warehouses'));
    }

    /**
     * Update the specified return
     */
    public function update(Request $request, StockTransaction $return): RedirectResponse
    {
        // Ensure this is a return transaction
        if (!$return->isReturn()) {
            abort(404, 'Return not found');
        }

        // Only allow updating of pending returns
        if (!$return->isPending()) {
            return redirect()->route('returns.show', $return->id)
                ->with('error', 'Only pending returns can be updated.');
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
            'return_reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Re-check the new quantity against the source document, ignoring
            // this return's own current quantity.
            $this->returnService->assertReturnQuantityIsValid(
                $return->transaction_type,
                (int) $return->reference_id,
                (int) $return->product_id,
                (int) $request->quantity,
                $return->id
            );

            $returnData = is_array($return->return_data) ? $return->return_data : [];
            $returnData['internal_notes'] = $request->notes;

            // `notes` holds the return reason and is read back by
            // getReturnReasonAttribute() - it must stay plain text.
            $return->update([
                'quantity' => $request->quantity,
                'notes' => $request->return_reason,
                'return_data' => $returnData,
            ]);

            return redirect()->route('returns.show', $return->id)
                ->with('success', 'Return updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Get customer invoices for AJAX
     */
    public function getCustomerInvoices(Customer $customer): JsonResponse
    {
        try {
            $invoices = $this->returnService->getAvailableInvoices($customer);
            return response()->json(['invoices' => $invoices]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get vendor supplier bills for AJAX
     */
    public function getVendorSupplierBills(Vendor $vendor): JsonResponse
    {
        try {
            $supplierBills = $this->returnService->getAvailableSupplierBills($vendor);
            return response()->json(['supplier_bills' => $supplierBills]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get retailer stock transfers for AJAX
     */
    public function getRetailerStockTransfers(Retailer $retailer): JsonResponse
    {
        try {
            $stockTransfers = $this->returnService->getAvailableStockTransfers($retailer);
            return response()->json(['stock_transfers' => $stockTransfers]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get invoice items for AJAX
     */
    public function getInvoiceItems(Invoice $invoice): JsonResponse
    {
        try {
            $items = $this->returnService->getInvoiceItems($invoice);
            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get supplier bill items for AJAX
     */
    public function getSupplierBillItems(SupplierBill $supplierBill): JsonResponse
    {
        try {
            $items = $this->returnService->getSupplierBillItems($supplierBill);
            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stock transfer items for AJAX
     */
    public function getStockTransferItems(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            // Validate that the stock transfer is completed
            if ($stockTransfer->status !== 'completed') {
                return response()->json([
                    'error' => 'Only completed stock transfers can be used for retailer returns. Current status: ' . ucfirst($stockTransfer->status)
                ], 400);
            }

            $items = $this->returnService->getStockTransferItems($stockTransfer);
            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate return quantity for AJAX
     */
    public function validateReturnQuantity(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'return_type' => 'required|string',
                'reference_id' => 'required|integer',
                'product_id' => 'required|integer',
                'quantity' => 'required|integer|min:1',
            ]);

            $result = $this->returnService->validateReturnQuantity(
                $request->return_type,
                $request->reference_id,
                $request->product_id,
                $request->quantity
            );

            // Ensure the result has the expected structure
            if (!isset($result['valid']) || !isset($result['errors'])) {
                return response()->json([
                    'valid' => false,
                    'errors' => ['Unexpected response format']
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'errors' => ['Validation error: ' . $e->getMessage()]
            ]);
        }
    }

    /**
     * Get product return destination for AJAX
     */
    public function getProductReturnDestination(Request $request, int $referenceId, int $productId): JsonResponse
    {
        try {
            // Get return_type from query parameter
            $returnType = $request->query('return_type');
            
            if (!$returnType) {
                return response()->json(['error' => 'return_type parameter is required'], 400);
            }

            $result = $this->returnService->getProductReturnDestination(
                $returnType,
                $referenceId,
                $productId
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get invoice fulfillment location for AJAX
     */
    public function getInvoiceFulfillmentLocation(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|integer',
        ]);

        $result = $this->returnService->getInvoiceFulfillmentLocation($request->invoice_id);
        return response()->json($result);
    }

    /**
     * Approve a return
     */
    public function approve(StockTransaction $return): RedirectResponse
    {
        try {
            $this->returnService->approveReturn($return);
            
            // For customer returns, redirect to the generated credit note
            if ($return->transaction_type === StockTransaction::TYPE_CUSTOMER_RETURN) {
                $creditNote = \App\Models\CreditNote::where('return_transaction_id', $return->id)->first();
                if ($creditNote) {
                    return redirect()->route('credit-notes.show', $creditNote)
                        ->with('success', 'Return approved and credit note generated successfully.');
                }
            }
            
            // For vendor returns, redirect to the generated debit note
            if ($return->transaction_type === StockTransaction::TYPE_VENDOR_RETURN) {
                $debitNote = \App\Models\DebitNote::where('return_transaction_id', $return->id)->first();
                if ($debitNote) {
                    return redirect()->route('debit-notes.show', $debitNote)
                        ->with('success', 'Return approved and debit note generated successfully.');
                }
            }
            
            // For retailer returns, show specific success message with stock adjustment details
            if ($return->transaction_type === StockTransaction::TYPE_RETAILER_RETURN) {
                // Get warehouse information for the success message
                $warehouse = null;
                if ($return->stockTransfer && $return->stockTransfer->from_location_type === 'App\\Models\\Warehouse') {
                    $warehouse = \App\Models\Warehouse::find($return->stockTransfer->from_location_id);
                }
                
                $successMessage = 'Retailer return approved successfully. ';
                $successMessage .= 'Stock has been adjusted: ';
                $successMessage .= 'Decreased ' . number_format($return->quantity) . ' units from ' . ($return->location->name ?? 'Retailer') . ', ';
                $successMessage .= 'Increased ' . number_format($return->quantity) . ' units to ' . ($warehouse ? $warehouse->name : 'Warehouse') . '. ';
                $successMessage .= 'A draft journal entry was created to move the inventory value back to the warehouse.';
                
                return back()->with('success', $successMessage);
            }
            
            // For other returns or if no note was generated, redirect back
            return back()->with('success', 'Return approved successfully.');
        } catch (\Exception $e) {
            \Log::error('Return approval failed:', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a return
     */
    public function reject(Request $request, StockTransaction $return): RedirectResponse
    {
        if (!$return->isReturn()) {
            abort(404, 'Return not found');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->returnService->rejectReturn($return, $request->rejection_reason);

            return back()->with('success', 'Return rejected. No stock or accounting entries were made.');
        } catch (\Exception $e) {
            \Log::error('Return rejection failed:', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Complete a return
     */
    public function complete(StockTransaction $return): RedirectResponse
    {
        try {
            $this->returnService->completeReturn($return);
            return back()->with('success', 'Return completed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Delete a return
     */
    public function destroy(StockTransaction $return): RedirectResponse
    {
        // Ensure this is a return transaction
        if (!$return->isReturn()) {
            abort(404, 'Return not found');
        }

        // Only allow deletion of pending returns
        if (!$return->isPending()) {
            return back()->with('error', 'Only pending returns can be deleted.');
        }

        try {
            $return->delete();
            return redirect()->route('returns.index')->with('success', 'Return deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
} 