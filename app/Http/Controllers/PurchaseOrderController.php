<?php

namespace App\Http\Controllers;

use App\Exceptions\DataNotFoundException;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $orders = $this->service->list($request->only(['status', 'vendor_id']));

        return view('purchase-orders.index', [
            'orders' => $orders,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseOrder::statuses(),
            'filters' => $request->only(['status', 'vendor_id']),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', [
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->service->createWithItems($request->validated());

            return redirect()->route('purchase-orders.show', $order->id)
                ->with('success', 'Purchase order created. Approve it to send it to the vendor.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create the purchase order. Please try again.');
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            return view('purchase-orders.show', ['order' => $this->service->get($id)]);
        } catch (DataNotFoundException $e) {
            return redirect()->route('purchase-orders.index')->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $order = $this->service->get($id);

            if (! $order->isEditable()) {
                return redirect()->route('purchase-orders.show', $id)
                    ->with('error', 'Only a draft purchase order can be edited.');
            }

            return view('purchase-orders.edit', [
                'order' => $order,
                'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'assignableProducts' => $this->service->assignableProducts($order->vendor_id),
            ]);
        } catch (DataNotFoundException $e) {
            return redirect()->route('purchase-orders.index')->with('error', $e->getMessage());
        }
    }

    public function update(UpdatePurchaseOrderRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->updateWithItems($id, $request->validated());

            return redirect()->route('purchase-orders.show', $id)->with('success', 'Purchase order updated.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('purchase-orders.index')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(int $id): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->service->approve($id),
            $id,
            'Purchase order approved. Send it to the vendor when you are ready.'
        );
    }

    public function send(int $id): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->service->send($id),
            $id,
            'Purchase order sent to the vendor. Record the supply when the goods arrive.'
        );
    }

    public function cancel(int $id): RedirectResponse
    {
        return $this->runTransition(fn () => $this->service->cancel($id), $id, 'Purchase order cancelled.');
    }

    /**
     * The vendor's price list, for the line editor to prefill costs from.
     */
    public function vendorProducts(int $vendor): JsonResponse
    {
        return response()->json($this->service->assignableProducts($vendor));
    }

    /**
     * Shared shape for the approve/send/cancel buttons.
     */
    private function runTransition(callable $operation, int $id, string $message): RedirectResponse
    {
        try {
            $operation();

            return redirect()->route('purchase-orders.show', $id)->with('success', $message);
        } catch (DataNotFoundException $e) {
            return redirect()->route('purchase-orders.index')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
