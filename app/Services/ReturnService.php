<?php

namespace App\Services;

use App\Models\StockTransaction;
use App\Models\ProductStock;
use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Models\StockTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Retailer;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Account;
use App\Services\AccountingService;
use App\Traits\HasErrorHandling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ReturnService
{
    use HasErrorHandling;

    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get all return transactions with their related data
     */
    public function getAllReturns(array $filters = [], int $perPage = 20)
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = StockTransaction::with([
                    'product',
                    'location',
                    'reference'
                ])->whereIn('transaction_type', StockTransaction::RETURN_TYPES);

                // Apply filters
                if (isset($filters['type']) && !empty($filters['type'])) {
                    $query->where('transaction_type', $filters['type']);
                }

                if (isset($filters['location_id']) && !empty($filters['location_id'])) {
                    $query->where('location_id', $filters['location_id']);
                }

                if (isset($filters['product_id']) && !empty($filters['product_id'])) {
                    $query->where('product_id', $filters['product_id']);
                }

                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                    $query->where('transaction_date', '>=', $filters['date_from']);
                }

                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $query->where('transaction_date', '<=', $filters['date_to']);
                }

                return $query->orderBy('transaction_date', 'desc')->paginate($perPage);
            },
            StockTransaction::class,
            $perPage,
            $filters
        );
    }

    /**
     * Get return statistics
     */
    public function getReturnStatistics(): array
    {
        return $this->handleServiceOperation(
            function() {
                // Get counts for each return type
                $customerReturnsCount = StockTransaction::where('transaction_type', 'customer_return')->count();
                $vendorReturnsCount = StockTransaction::where('transaction_type', 'vendor_return')->count();
                $retailerReturnsCount = StockTransaction::where('transaction_type', 'retailer_return')->count();
                
                // Get quantities for each return type
                $customerReturnsQuantity = StockTransaction::where('transaction_type', 'customer_return')->sum('quantity');
                $vendorReturnsQuantity = StockTransaction::where('transaction_type', 'vendor_return')->sum('quantity');
                $retailerReturnsQuantity = StockTransaction::where('transaction_type', 'retailer_return')->sum('quantity');
                
                // Calculate values (for now, using a placeholder calculation)
                // In a real system, you'd calculate based on product costs/prices
                $customerReturnsValue = 0; // Placeholder - would calculate from return amounts
                $vendorReturnsValue = 0;   // Placeholder - would calculate from return amounts
                $retailerReturnsValue = 0; // Placeholder - would calculate from return amounts

                return [
                    'customer_returns' => [
                        'count' => $customerReturnsCount,
                        'quantity' => $customerReturnsQuantity,
                        'value' => $customerReturnsValue,
                    ],
                    'vendor_returns' => [
                        'count' => $vendorReturnsCount,
                        'quantity' => $vendorReturnsQuantity,
                        'value' => $vendorReturnsValue,
                    ],
                    'retailer_returns' => [
                        'count' => $retailerReturnsCount,
                        'quantity' => $retailerReturnsQuantity,
                        'value' => $retailerReturnsValue,
                    ],
                ];
            },
            'return statistics'
        );
    }

    /**
     * Create a customer return from an invoice
     */
    public function createCustomerReturn(array $data): StockTransaction
    {
        return DB::transaction(function () use ($data) {
            // Validate required fields
            if (!isset($data['invoice_id']) || !isset($data['return_location_type']) || 
                !isset($data['return_location_id']) || !isset($data['product_id']) || 
                !isset($data['quantity'])) {
                throw new \InvalidArgumentException('Missing required fields for customer return');
            }
            
            $invoice = Invoice::with(['items.product'])->findOrFail($data['invoice_id']);
            $returnLocation = $this->getLocation($data['return_location_type'], $data['return_location_id']);
            $product = Product::findOrFail($data['product_id']);
            
            // Calculate return amount
            $invoiceItem = $invoice->items->where('product_id', $data['product_id'])->first();
            $returnAmount = $invoiceItem ? ($invoiceItem->unit_price * $data['quantity']) : 0;
            
            $transaction = StockTransaction::create([
                'product_id' => $data['product_id'],
                'location_id' => $returnLocation->id,
                'location_type' => get_class($returnLocation),
                'quantity' => $data['quantity'],
                'direction' => 'inbound',
                'transaction_type' => StockTransaction::TYPE_CUSTOMER_RETURN,
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'transaction_date' => $data['return_date'] ?? now(),
                'status' => StockTransaction::STATUS_PENDING,
                'notes' => $data['return_reason'] ?? null,
            ]);

            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Create a vendor return from a supplier bill
     */
    public function createVendorReturn(array $data): StockTransaction
    {
        return DB::transaction(function () use ($data) {
            // Validate required fields
            if (!isset($data['supplier_bill_id']) || !isset($data['vendor_id']) || 
                !isset($data['product_id']) || !isset($data['quantity'])) {
                throw new \InvalidArgumentException('Missing required fields for vendor return');
            }
            
            $supplierBill = SupplierBill::with(['items.product', 'vendor', 'grn.supply.warehouse'])->findOrFail($data['supplier_bill_id']);
            $vendor = Vendor::findOrFail($data['vendor_id']);
            $product = Product::findOrFail($data['product_id']);
            
            // Get the warehouse from the supplier bill's GRN's supply
            $warehouse = $supplierBill->grn->supply->warehouse ?? null;
            if (!$warehouse) {
                throw new \InvalidArgumentException('No warehouse found for this supplier bill');
            }
            
            // Calculate return amount
            $billItem = $supplierBill->items->where('product_id', $data['product_id'])->first();
            $returnAmount = $billItem ? ($billItem->unit_cost * $data['quantity']) : 0;
            
            $transaction = StockTransaction::create([
                'product_id' => $data['product_id'],
                'location_id' => $warehouse->id, // Source: Warehouse (where stock will be decreased)
                'location_type' => Warehouse::class,
                'quantity' => $data['quantity'],
                'direction' => 'outbound', // Stock going out from warehouse
                'transaction_type' => StockTransaction::TYPE_VENDOR_RETURN,
                'reference_type' => SupplierBill::class,
                'reference_id' => $supplierBill->id,
                'transaction_date' => $data['return_date'] ?? now(),
                'status' => StockTransaction::STATUS_PENDING,
                'notes' => $data['return_reason'] ?? null,
            ]);

            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Create a retailer return from a stock transfer
     */
    public function createRetailerReturn(array $data): StockTransaction
    {
        return DB::transaction(function () use ($data) {
            // Validate required fields
            if (!isset($data['stock_transfer_id']) || !isset($data['warehouse_id']) || 
                !isset($data['product_id']) || !isset($data['quantity'])) {
                throw new \InvalidArgumentException('Missing required fields for retailer return');
            }
            
            $stockTransfer = StockTransfer::with(['items.product', 'fromLocation', 'toLocation'])->findOrFail($data['stock_transfer_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $product = Product::findOrFail($data['product_id']);
            
            // For retailer returns, the source is the retailer and destination is the warehouse
            // Manually load the retailer since polymorphic relationship might not work correctly
            $retailer = null;
            if ($stockTransfer->to_location_type === 'App\\Models\\Retailer' || $stockTransfer->to_location_type === 'App\Models\Retailer') {
                $retailer = Retailer::find($stockTransfer->to_location_id);
            }
            
            // Validate that the toLocation exists and is a retailer
            if (!$retailer) {
                throw new \InvalidArgumentException('Stock transfer does not have a valid destination location');
            }
            
            if (!($retailer instanceof Retailer)) {
                throw new \InvalidArgumentException('Stock transfer destination is not a retailer');
            }
            
            // Calculate return amount (using product cost)
            $returnAmount = $product->purchase_price ? ($product->purchase_price * $data['quantity']) : 0;
            
            $transaction = StockTransaction::create([
                'product_id' => $data['product_id'],
                'location_id' => $retailer->id, // Source: Retailer
                'location_type' => get_class($retailer),
                'quantity' => $data['quantity'],
                'direction' => 'outbound', // Stock going out from retailer
                'transaction_type' => StockTransaction::TYPE_RETAILER_RETURN,
                'reference_type' => StockTransfer::class,
                'reference_id' => $stockTransfer->id,
                'transaction_date' => $data['return_date'] ?? now(),
                'status' => StockTransaction::STATUS_ISSUED, // Set status as issued for retailer returns
                'notes' => $data['return_reason'] ?? null,
            ]);

            // Log the creation for debugging
            Log::info('Retailer return created', [
                'transaction_id' => $transaction->id,
                'stock_transfer_id' => $stockTransfer->id,
                'retailer_id' => $retailer->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'status' => $transaction->status
            ]);

            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Get available invoices for customer returns
     */
    public function getAvailableInvoices(Customer $customer): Collection
    {
        return Invoice::with(['items.product'])
            ->where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereHas('items', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->orderBy('invoice_date', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total' => $invoice->total,
                    'items_count' => $invoice->items->count(),
                ];
            });
    }

    /**
     * Get available supplier bills for vendor returns
     */
    public function getAvailableSupplierBills(Vendor $vendor): Collection
    {
        return SupplierBill::with(['items.product'])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->whereHas('items', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->orderBy('bill_date', 'desc')
            ->get()
            ->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->formatted_id,
                    'bill_date' => $bill->bill_date,
                    'total' => $bill->total_amount,
                    'items_count' => $bill->items->count(),
                ];
            });
    }

    /**
     * Get available stock transfers for retailer returns
     */
    public function getAvailableStockTransfers(Retailer $retailer): Collection
    {
        return StockTransfer::with(['items.product'])
            ->where('to_location_type', Retailer::class)
            ->where('to_location_id', $retailer->id)
            ->where('status', 'completed') // Only show completed stock transfers
            ->whereHas('items', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->orderBy('transfer_date', 'desc')
            ->get()
            ->map(function ($transfer) {
                return [
                    'id' => $transfer->id,
                    'transfer_number' => $transfer->formatted_id,
                    'transfer_date' => $transfer->transfer_date,
                    'items_count' => $transfer->items->count(),
                    'status' => $transfer->status, // Include status for validation
                ];
            });
    }

    /**
     * Get invoice items for return
     */
    public function getInvoiceItems(Invoice $invoice): Collection
    {
        try {
            return $invoice->items()->with(['product'])->get()->map(function ($item) use ($invoice) {
                // Calculate already returned quantity
                $alreadyReturned = StockTransaction::where('transaction_type', StockTransaction::TYPE_CUSTOMER_RETURN)
                    ->where('reference_type', Invoice::class)
                    ->where('reference_id', $invoice->id)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity') ?? 0;

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown Product',
                    'product_sku' => $item->product->sku ?? 'N/A',
                    'quantity_sold' => $item->quantity,
                    'already_returned' => $alreadyReturned,
                    'quantity_available_for_return' => $item->quantity - $alreadyReturned,
                    'unit_price' => $item->unit_price,
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Error in getInvoiceItems: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get supplier bill items for return
     */
    public function getSupplierBillItems(SupplierBill $supplierBill): Collection
    {
        try {
            return $supplierBill->items()->with(['product'])->get()->map(function ($item) use ($supplierBill) {
                // Calculate already returned quantity
                $alreadyReturned = StockTransaction::where('transaction_type', StockTransaction::TYPE_VENDOR_RETURN)
                    ->where('reference_type', SupplierBill::class)
                    ->where('reference_id', $supplierBill->id)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity') ?? 0;

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown Product',
                    'product_sku' => $item->product->sku ?? 'N/A',
                    'quantity_purchased' => $item->quantity,
                    'already_returned' => $alreadyReturned,
                    'quantity_available_for_return' => $item->quantity - $alreadyReturned,
                    'unit_cost' => $item->unit_cost,
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Error in getSupplierBillItems: ' . $e->getMessage(), [
                'supplier_bill_id' => $supplierBill->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get stock transfer items for return
     */
    public function getStockTransferItems(StockTransfer $stockTransfer): Collection
    {
        try {
            // Validate that the stock transfer is completed
            if ($stockTransfer->status !== 'completed') {
                throw new \Exception("Only completed stock transfers can be used for retailer returns. Current status: " . ucfirst($stockTransfer->status));
            }

            return $stockTransfer->items()->with(['product'])->get()->map(function ($item) use ($stockTransfer) {
                // Calculate already returned quantity
                $alreadyReturned = StockTransaction::where('transaction_type', StockTransaction::TYPE_RETAILER_RETURN)
                    ->where('reference_type', StockTransfer::class)
                    ->where('reference_id', $stockTransfer->id)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity') ?? 0;

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown Product',
                    'product_sku' => $item->product->sku ?? 'N/A',
                    'quantity_transferred' => $item->quantity,
                    'already_returned' => $alreadyReturned,
                    'quantity_available_for_return' => $item->quantity - $alreadyReturned,
                    'unit_cost' => 0, // Stock transfers don't have unit costs
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Error in getStockTransferItems: ' . $e->getMessage(), [
                'stock_transfer_id' => $stockTransfer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Validate return quantity
     */
    public function validateReturnQuantity(string $returnType, int $referenceId, int $productId, int $quantity, ?int $excludeTransactionId = null): array
    {
        $errors = [];

        // Basic validation
        if ($quantity <= 0) {
            $errors[] = "Return quantity must be greater than 0";
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        // Validate return type
        if (!in_array($returnType, [StockTransaction::TYPE_CUSTOMER_RETURN, StockTransaction::TYPE_VENDOR_RETURN, StockTransaction::TYPE_RETAILER_RETURN])) {
            $errors[] = "Invalid return type";
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        switch ($returnType) {
            case StockTransaction::TYPE_CUSTOMER_RETURN:
                $invoice = Invoice::with(['items'])->find($referenceId);
                if (!$invoice) {
                    $errors[] = "Invoice not found";
                    break;
                }

                $invoiceItem = $invoice->items->where('product_id', $productId)->first();
                if (!$invoiceItem) {
                    $errors[] = "Product not found in invoice";
                    break;
                }

                $alreadyReturned = $this->alreadyReturnedQuantity(
                    StockTransaction::TYPE_CUSTOMER_RETURN,
                    Invoice::class,
                    $referenceId,
                    $productId,
                    $excludeTransactionId
                );

                $availableQuantity = $invoiceItem->quantity - $alreadyReturned;
                if ($quantity > $availableQuantity) {
                    $errors[] = "Return quantity ({$quantity}) exceeds available quantity ({$availableQuantity}). Original: {$invoiceItem->quantity}, Already returned: {$alreadyReturned}";
                }
                break;

            case StockTransaction::TYPE_VENDOR_RETURN:
                $supplierBill = SupplierBill::with(['items'])->find($referenceId);
                if (!$supplierBill) {
                    $errors[] = "Supplier bill not found";
                    break;
                }

                $billItem = $supplierBill->items->where('product_id', $productId)->first();
                if (!$billItem) {
                    $errors[] = "Product not found in supplier bill";
                    break;
                }

                $alreadyReturned = $this->alreadyReturnedQuantity(
                    StockTransaction::TYPE_VENDOR_RETURN,
                    SupplierBill::class,
                    $referenceId,
                    $productId,
                    $excludeTransactionId
                );

                $availableQuantity = $billItem->quantity - $alreadyReturned;
                if ($quantity > $availableQuantity) {
                    $errors[] = "Return quantity ({$quantity}) exceeds available quantity ({$availableQuantity}). Original: {$billItem->quantity}, Already returned: {$alreadyReturned}";
                }
                break;

            case StockTransaction::TYPE_RETAILER_RETURN:
                $stockTransfer = StockTransfer::with(['items'])->find($referenceId);
                if (!$stockTransfer) {
                    $errors[] = "Stock transfer not found";
                    break;
                }

                // Validate that the stock transfer is completed
                if ($stockTransfer->status !== 'completed') {
                    $errors[] = "Only completed stock transfers can be used for retailer returns. Current status: " . ucfirst($stockTransfer->status);
                    break;
                }

                $transferItem = $stockTransfer->items->where('product_id', $productId)->first();
                if (!$transferItem) {
                    $errors[] = "Product not found in stock transfer";
                    break;
                }

                $alreadyReturned = $this->alreadyReturnedQuantity(
                    StockTransaction::TYPE_RETAILER_RETURN,
                    StockTransfer::class,
                    $referenceId,
                    $productId,
                    $excludeTransactionId
                );

                $availableQuantity = $transferItem->quantity - $alreadyReturned;
                if ($quantity > $availableQuantity) {
                    $errors[] = "Return quantity ({$quantity}) exceeds available quantity ({$availableQuantity}). Original: {$transferItem->quantity}, Already returned: {$alreadyReturned}";
                }
                break;

            default:
                $errors[] = "Invalid return type";
                break;
        }

        // Ensure we always return the expected structure
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * How much of a product has already been returned against a reference
     * document. Rejected and cancelled returns are excluded - they never moved
     * any stock, so they must not consume the remaining allowance.
     */
    private function alreadyReturnedQuantity(string $returnType, string $referenceType, int $referenceId, int $productId, ?int $excludeTransactionId = null): int
    {
        return (int) StockTransaction::where('transaction_type', $returnType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('product_id', $productId)
            ->whereNotIn('status', [
                StockTransaction::STATUS_REJECTED,
                StockTransaction::STATUS_CANCELLED,
            ])
            // When editing a return, its own quantity must not count against
            // the allowance it is being measured against.
            ->when($excludeTransactionId, fn ($query) => $query->where('id', '!=', $excludeTransactionId))
            ->sum('quantity');
    }

    /**
     * Validate a return line and throw if it is not allowed. Used by the
     * create/update paths so the rule holds regardless of what the browser
     * sent - the AJAX endpoint is only there for live feedback in the form.
     */
    public function assertReturnQuantityIsValid(string $returnType, int $referenceId, int $productId, int $quantity, ?int $excludeTransactionId = null): void
    {
        $result = $this->validateReturnQuantity($returnType, $referenceId, $productId, $quantity, $excludeTransactionId);

        if (!($result['valid'] ?? false)) {
            throw new \InvalidArgumentException(implode(' ', $result['errors'] ?? ['Invalid return quantity.']));
        }
    }

    /**
     * Get product return destination for AJAX
     */
    public function getProductReturnDestination(string $returnType, int $referenceId, int $productId): array
    {
        try {
            switch ($returnType) {
                case StockTransaction::TYPE_CUSTOMER_RETURN:
                    $invoice = Invoice::with(['order.fulfillmentLocation'])->find($referenceId);
                    if (!$invoice || !$invoice->order) {
                        return ['error' => 'Invoice or order not found'];
                    }

                    // Try to get fulfillment location from the loaded relationship first
                    $fulfillmentLocation = $invoice->order->fulfillmentLocation;
                    
                    // If not loaded, try to load it manually
                    if (!$fulfillmentLocation) {
                        $locationId = $invoice->order->fulfillment_location_id;
                        $locationType = $invoice->order->fulfillment_location_type;
                        
                        \Log::info('Manual fulfillment location loading', [
                            'invoice_id' => $invoice->id,
                            'order_id' => $invoice->order->id,
                            'location_id' => $locationId,
                            'location_type' => $locationType
                        ]);
                        
                        // Handle different possible class name formats
                        if ($locationType === 'App\\Models\\Warehouse' || $locationType === 'App\Models\Warehouse') {
                            $fulfillmentLocation = \App\Models\Warehouse::find($locationId);
                        } elseif ($locationType === 'App\\Models\\Retailer' || $locationType === 'App\Models\Retailer') {
                            $fulfillmentLocation = \App\Models\Retailer::find($locationId);
                        } elseif ($locationType === 'Warehouse') {
                            $fulfillmentLocation = \App\Models\Warehouse::find($locationId);
                        } elseif ($locationType === 'Retailer') {
                            $fulfillmentLocation = \App\Models\Retailer::find($locationId);
                        }
                        
                        if ($fulfillmentLocation) {
                            \Log::info('Successfully loaded fulfillment location manually', [
                                'location_id' => $fulfillmentLocation->id,
                                'location_name' => $fulfillmentLocation->name,
                                'location_class' => get_class($fulfillmentLocation)
                            ]);
                        } else {
                            \Log::error('Failed to load fulfillment location manually', [
                                'location_id' => $locationId,
                                'location_type' => $locationType
                            ]);
                        }
                    }
                    
                    if (!$fulfillmentLocation) {
                        return ['error' => 'No fulfillment location found'];
                    }

                    return [
                        'return_destination' => [
                            'id' => $fulfillmentLocation->id,
                            'name' => $fulfillmentLocation->name,
                            'type' => get_class($fulfillmentLocation),
                            'type_name' => class_basename($fulfillmentLocation),
                        ]
                    ];

                case StockTransaction::TYPE_VENDOR_RETURN:
                    $supplierBill = SupplierBill::with(['vendor'])->find($referenceId);
                    if (!$supplierBill || !$supplierBill->vendor) {
                        return ['error' => 'Supplier bill or vendor not found'];
                    }

                    return [
                        'return_destination' => [
                            'id' => $supplierBill->vendor->id,
                            'name' => $supplierBill->vendor->name,
                            'type' => Vendor::class,
                            'type_name' => 'Vendor',
                        ]
                    ];

                case StockTransaction::TYPE_RETAILER_RETURN:
                    $stockTransfer = StockTransfer::find($referenceId);
                    if (!$stockTransfer) {
                        return ['error' => 'Stock transfer not found'];
                    }

                    // Validate that the stock transfer is completed
                    if ($stockTransfer->status !== 'completed') {
                        return ['error' => 'Only completed stock transfers can be used for retailer returns. Current status: ' . ucfirst($stockTransfer->status)];
                    }

                    // Manually load the warehouse based on from_location_id and from_location_type
                    $warehouse = null;
                    if ($stockTransfer->from_location_type === 'App\\Models\\Warehouse' || $stockTransfer->from_location_type === 'App\Models\Warehouse') {
                        $warehouse = \App\Models\Warehouse::find($stockTransfer->from_location_id);
                    }

                    if (!$warehouse) {
                        return ['error' => 'Warehouse not found for this stock transfer'];
                    }

                    return [
                        'return_destination' => [
                            'id' => $warehouse->id,
                            'name' => $warehouse->name,
                            'type' => get_class($warehouse),
                            'type_name' => class_basename($warehouse),
                        ]
                    ];

                default:
                    return ['error' => 'Invalid return type'];
            }
        } catch (\Exception $e) {
            \Log::error('Error in getProductReturnDestination: ' . $e->getMessage(), [
                'return_type' => $returnType,
                'reference_id' => $referenceId,
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Error retrieving return destination: ' . $e->getMessage()];
        }
    }

    /**
     * Get invoice fulfillment location for AJAX
     */
    public function getInvoiceFulfillmentLocation(int $invoiceId): array
    {
        $invoice = Invoice::with(['order'])->find($invoiceId);
        if (!$invoice || !$invoice->order) {
            return ['error' => 'Invoice or order not found'];
        }

        $fulfillmentLocation = $invoice->order->fulfillment_location;
        if (!$fulfillmentLocation) {
            return ['error' => 'No fulfillment location found'];
        }

        return [
            'location_type' => get_class($fulfillmentLocation),
            'location_id' => $fulfillmentLocation->id,
            'location_name' => $fulfillmentLocation->name,
        ];
    }

    /**
     * Approve a return transaction
     */
    public function approveReturn(StockTransaction $transaction): StockTransaction
    {
        // For retailer returns, allow approval from issued or pending status
        if ($transaction->isRetailerReturn()) {
            if (!$transaction->isIssued() && !$transaction->isPending()) {
                throw new \Exception('Only issued or pending retailer returns can be approved');
            }
        } else {
            // For other return types, only allow approval from pending status
            if (!$transaction->isPending()) {
                throw new \Exception('Only pending returns can be approved');
            }
        }

        return DB::transaction(function () use ($transaction) {
            // Use the model's approve method with fallback for unauthenticated users
            $userId = auth()->id() ?? 1; // Default to user ID 1 if not authenticated
            $transaction->approve($userId);
            
            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Reject a return transaction. Nothing to unwind: stock is only posted
     * once a return is approved.
     */
    public function rejectReturn(StockTransaction $transaction, string $reason): StockTransaction
    {
        if ($transaction->isRetailerReturn()) {
            if (!$transaction->isIssued() && !$transaction->isPending()) {
                throw new \Exception('Only issued or pending retailer returns can be rejected');
            }
        } else {
            if (!$transaction->isPending()) {
                throw new \Exception('Only pending returns can be rejected');
            }
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $userId = auth()->id() ?? 1; // Default to user ID 1 if not authenticated
            $transaction->reject($userId, $reason);

            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Complete a return transaction
     */
    public function completeReturn(StockTransaction $transaction): StockTransaction
    {
        if ($transaction->status !== StockTransaction::STATUS_APPROVED) {
            throw new \Exception('Only approved returns can be completed');
        }

        return DB::transaction(function () use ($transaction) {
            // Use the model's complete method with fallback for unauthenticated users
            $userId = auth()->id() ?? 1; // Default to user ID 1 if not authenticated
            $transaction->complete($userId);
            
            return $transaction->load(['product', 'location', 'reference']);
        });
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'type' => [
                'type' => 'select',
                'label' => 'Return Type',
                'options' => [
                    'customer_return' => 'Customer Return',
                    'vendor_return' => 'Vendor Return',
                    'retailer_return' => 'Retailer Return',
                ]
            ],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ]
            ],
            'product_id' => [
                'type' => 'select',
                'label' => 'Product',
                'options' => Product::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'From Date',
                'placeholder' => 'Select start date'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'To Date',
                'placeholder' => 'Select end date'
            ]
        ];
    }

    /**
     * Get sort options for the view
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'transaction_date' => 'Date',
            'transaction_type' => 'Type',
            'quantity' => 'Quantity',
            'status' => 'Status',
        ];
    }

    /**
     * Get page title based on return type
     */
    public function getPageTitle(?string $type = null): string
    {
        if (!$type) {
            return 'All Returns';
        }

        return match($type) {
            'customer_return' => 'Customer Returns',
            'vendor_return' => 'Vendor Returns',
            'retailer_return' => 'Retailer Returns',
            default => 'All Returns',
        };
    }

    /**
     * Helper method to get location by type and ID
     */
    private function getLocation(string $locationType, int $locationId)
    {
        return match($locationType) {
            'warehouse' => Warehouse::findOrFail($locationId),
            'retailer' => Retailer::findOrFail($locationId),
            'vendor' => Vendor::findOrFail($locationId),
            default => throw new \InvalidArgumentException('The selected return location type is invalid.'),
        };
    }
} 