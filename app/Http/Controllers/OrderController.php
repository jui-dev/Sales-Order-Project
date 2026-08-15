<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Exceptions\DataNotFoundException;
use App\Traits\HasApiResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly OrderService $service)
    {
    }

    public function index(Request $request): View
    {
        try {
            \Log::info('Starting to load orders...');

            // Check if filters are provided
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort' => $request->sort,
                'direction' => $request->direction,
            ];

            // Use filtered orders if filters are provided, otherwise use simple list
            if (array_filter($filters)) {
                $orders = $this->service->getFilteredOrders($filters, 25);
            } else {
                $orders = $this->service->list();
            }

            $filterOptions = $this->service->getFilterOptions();
            $sortOptions = $this->service->getSortOptions();

            \Log::info('Orders loaded successfully. Count: ' . $orders->count());
            return view('orders.index', compact('orders', 'filterOptions', 'sortOptions'));
        } catch (\Exception $e) {
            \Log::error('Error loading orders: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            // Return empty paginated result with proper structure
            $emptyOrders = \App\Models\Order::paginate(25);
            $emptyOrders->setCollection(collect());

            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout and the page both read the message
            // off the session, so the banner never rendered.
            session()->now('error', 'Unable to load orders. Please try again later.');

            return view('orders.index', [
                'orders' => $emptyOrders,
                'filterOptions' => $this->service->getFilterOptions(),
                'sortOptions' => $this->service->getSortOptions()
            ]);
        }
    }

    public function create(): View
    {
        // This method is not used because route is defined via closure in web.php
        abort(404);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->service->createWithItems($request->validated());
            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Order created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create order. Please try again.');
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $order = $this->service->get($id);

            // Loaded here rather than in the service so the API response shape
            // stays as it is: the detail page walks the items and reads the
            // workflow off the picking list and the invoice's payments.
            $order->load(['items.product', 'pickingList', 'invoice.payments']);

            return view('orders.show', compact('order'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('orders.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading order: ' . $e->getMessage());
            return redirect()->route('orders.index')
                ->with('error', 'Unable to load order details. Please try again later.');
        }
    }

    /**
     * Update order status (PATCH /orders/{id}/update-status)
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        try {
            $request->validate(['status' => 'required|string|in:confirmed,cancelled,processing,completed']);
            $order = $this->service->get($id);

            if ($request->status === 'confirmed' && $order->status !== 'confirmed') {
                try {
                    $pickingList = $this->service->confirm($order);
                    return redirect()->route('customer-picking.show', $pickingList->id)
                        ->with('success', 'Order confirmed and picking list created successfully. You can now process the picking.');
                } catch (\Throwable $e) {
                    return back()->with('error', $e->getMessage());
                }
            } elseif ($request->status === 'completed' && $order->status !== 'completed') {
                try {
                    // Start a database transaction
                    \DB::beginTransaction();

                    // Check if there's a completed picking list for this order
                    $pickingList = \App\Models\PickingList::where('reference_type', \App\Models\Order::class)
                        ->where('reference_id', $order->id)
                        ->where('status', 'completed')
                        ->first();

                    // Mark order as completed
                    $order->update(['status' => $request->status]);

                    // Only deduct stock if there's no completed picking list (manual completion)
                    // If there's a completed picking list, stock was already deducted by PickingListObserver
                    if (!$pickingList) {
                        $this->service->deductOrderStock($order);
                    }

                    // Generate invoice (if not already generated by picking completion)
                    if (!$order->invoice) {
                        $invoice = app(\App\Services\InvoiceService::class)->generateFromOrder($order);
                    } else {
                        $invoice = $order->invoice;
                    }

                    \DB::commit();

                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', 'Order marked as completed successfully.');
                } catch (\Throwable $e) {
                    \DB::rollBack();
                    return back()->with('error', 'Failed to complete order: ' . $e->getMessage());
                }
            } else {
                $order->update(['status' => $request->status]);
                return back()->with('success', 'Order status updated successfully.');
            }
        } catch (DataNotFoundException $e) {
            return redirect()->route('orders.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to update order status. Please try again.');
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $order = $this->service->get($id);
            return view('orders.edit', compact('order'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('orders.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('orders.index')
                ->with('error', 'Unable to load order for editing. Please try again later.');
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        try {
            $order = $this->service->update($id, $request->validated());
            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Order updated successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('orders.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to update order. Please try again.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->service->delete($id);
            return redirect()->route('orders.index')
                ->with('success', 'Order deleted successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('orders.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('orders.index')
                ->with('error', 'Unable to delete order. Please try again.');
        }
    }

    /**
     * API endpoint for getting orders (for AJAX requests)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'search' => $request->search,
                    'status' => $request->status,
                    'customer_id' => $request->customer_id,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'sort' => $request->sort,
                    'direction' => $request->direction,
                ];

                $perPage = $request->get('per_page', 20);

                if (array_filter($filters)) {
                    return $this->service->getFilteredOrders($filters, $perPage);
                } else {
                    return $this->service->list();
                }
            },
            'orders',
            'Orders retrieved successfully'
        );
    }

    /**
     * API endpoint for getting a single order
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->service->get($id);
            },
            'order',
            'Order retrieved successfully'
        );
    }
}
