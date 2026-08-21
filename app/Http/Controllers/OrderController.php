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

    /**
     * The order form.
     *
     * Products no longer carry their price into the markup. What a customer
     * pays depends on who they are, which channel they are buying through and
     * how many they want - none of which is known until the form is filled in -
     * so the price is fetched from priceQuote() as those choices are made.
     */
    public function create(): View
    {
        // Only what can actually be sold. available_stocks is maintained by
        // ProductStockObserver.
        $products = \App\Models\Product::query()
            ->where('available_stocks', '>', 0)
            ->orderBy('name')
            ->get();

        // Whether each one has a price at all. Resolved without a context on
        // purpose: the customer, channel and quantity are not known until the
        // form is filled in, and a product with no price and no cost behind it
        // has none under any of them. Marked rather than filtered out, so the
        // reason it cannot be ordered is visible on the form.
        $prices = app(\App\Services\Pricing\PriceResolver::class)->forSaleMany($products->all());

        $products = $products->map(function ($product) use ($prices) {
            $product->current_stock = $product->available_stocks;
            $product->is_priced = ($prices[$product->id] ?? null) !== null;

            return $product;
        });

        return view('orders.create', [
            'customers' => \App\Models\Customer::orderBy('name')->get(),
            'products' => $products,
            'warehouses' => \App\Models\Warehouse::all(),
            'retailers' => \App\Models\Retailer::all(),
            'salesChannels' => \App\Models\SalesChannel::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * What this customer pays for this product, right now.
     *
     * The order form asks as the buyer, product and quantity are chosen. The
     * answer carries its own provenance so the form can say where the figure
     * came from, and so store() can check the posted price against the same
     * resolution rather than trusting the browser.
     */
    public function priceQuote(Request $request, \App\Services\Pricing\PriceResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sales_channel_id' => ['nullable', 'exists:sales_channels,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            // What the customer pays depends on where their order is fulfilled
            // from, so the form has to say which it picked.
            'fulfillment_location_id' => ['nullable', 'integer'],
            'fulfillment_location_type' => ['nullable', 'in:warehouse,retailer,other'],
        ]);

        $product = \App\Models\Product::findOrFail($data['product_id']);

        $price = $resolver->forSale($product, new \App\Services\Pricing\PriceContext(
            customer: isset($data['customer_id']) ? \App\Models\Customer::find($data['customer_id']) : null,
            salesChannel: isset($data['sales_channel_id'])
                ? \App\Models\SalesChannel::find($data['sales_channel_id'])
                : null,
            fulfilmentLocation: $this->fulfilmentLocation(
                $data['fulfillment_location_type'] ?? null,
                $data['fulfillment_location_id'] ?? null,
            ),
            quantity: (int) ($data['quantity'] ?? 1),
        ));

        if (! $price) {
            return response()->json([
                'priced' => false,
                'message' => 'No price has been agreed for this product yet.',
            ]);
        }

        return response()->json([
            'priced' => true,
            'unit_price' => round($price->unitPrice, 2),
            'price_list_item_id' => $price->priceListItemId,
            'price_list_name' => $price->priceListName,
            'derived' => $price->isDerived(),
        ]);
    }

    /**
     * Turn the form's location type/id pair into the model it names.
     *
     * The sale price lists are assigned to Warehouse and Retailer as classes,
     * so the resolver only needs an instance of the right kind - but it is the
     * real record, so a rate agreed for one specific store can outrank the
     * price every store gets without changing anything here.
     */
    private function fulfilmentLocation(?string $type, ?int $id): ?object
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'warehouse' => \App\Models\Warehouse::find($id),
            'retailer' => \App\Models\Retailer::find($id),
            default => null,
        };
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
            $order = Order::with(['orderItems.product', 'customer'])->findOrFail($id);

            return view('orders.edit', [
                'order' => $order,
                'customers' => \App\Models\Customer::orderBy('name')->get(),
                'products' => \App\Models\Product::orderBy('name')->get(),
                'warehouses' => \App\Models\Warehouse::all(),
                'retailers' => \App\Models\Retailer::all(),
                'salesChannels' => \App\Models\SalesChannel::where('is_active', true)->orderBy('name')->get(),
            ]);
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
