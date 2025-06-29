<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service)
    {
    }

    public function index(): View
    {
        $orders = \App\Models\Order::with(['customer', 'items'])->latest()->paginate(15);
        return view('orders.index', compact('orders'));
    }

    public function create(): View
    {
        // This method is not used because route is defined via closure in web.php
        abort(404);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->service->createWithItems($request->validated());

        return redirect()->route('orders.show', $order->id)
            ->with('success', 'Order created successfully.');
    }

    public function show(int $id): View
    {
        $order = $this->service->get($id);
        return view('orders.show', compact('order'));
    }

    /**
     * Update order status (PATCH /orders/{id}/update-status)
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate(['status' => 'required|string|in:confirmed,cancelled,processing']);
        $order = $this->service->get($id);

        if ($request->status === 'confirmed' && $order->status !== 'confirmed') {
            try {
                $pickingList = $this->service->confirm($order);
            } catch (\Throwable $e) {
                return back()->with('error', $e->getMessage());
            }

            // Determine redirect based on first item location_type
            $firstItemLocationType = optional($order->items->first())->location_type;
            if ($firstItemLocationType === \App\Models\Retailer::class) {
                $pickingPath = route('retailer-to-customer-picking.show', $pickingList->id);
            } else {
                $pickingPath = route('warehouse-to-customer-picking.show', $pickingList->id);
            }

            return redirect($pickingPath)->with('success', 'Order confirmed and picking list generated.');
        }

        // Fallback: simply update status
        $order->update(['status' => $request->status]);
        return back()->with('success', 'Order status updated.');
    }
} 