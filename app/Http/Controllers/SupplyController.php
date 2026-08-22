<?php

namespace App\Http\Controllers;

use App\Exceptions\DataNotFoundException;
use App\Http\Requests\StoreSupplyRequest;
use App\Http\Requests\UpdateSupplyRequest;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Services\PurchaseOrderService;
use App\Services\SupplyService;
use App\Traits\HasApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyController extends Controller
{
    use HasApiResponses;

    public function __construct(
        private readonly SupplyService $service,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function index(Request $request): View
    {
        try {
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'vendor_id' => $request->vendor_id,
                'warehouse_id' => $request->warehouse_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort' => $request->sort,
                'direction' => $request->direction,
            ];

            $supplies = $this->service->getFilteredSupplies($filters, 20);
            $filterOptions = $this->service->getFilterOptions();
            $sortOptions = $this->service->getSortOptions();
            $awaitingOrdersCount = $this->awaitingOrdersCount($request);

            return view('supplies.index', compact(
                'supplies',
                'filterOptions',
                'sortOptions',
                'awaitingOrdersCount'
            ));
        } catch (\Exception $e) {
            \Log::error('Error loading supplies: '.$e->getMessage());
            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout reads the message off the session,
            // so the toast never rendered.
            session()->now('error', 'Unable to load supplies. Please try again later.');

            return view('supplies.index', [
                'supplies' => collect(),
                'filterOptions' => [],
                'sortOptions' => [],
                'awaitingOrdersCount' => 0,
            ]);
        }
    }

    /**
     * API endpoint to get all supplies
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function () use ($request) {
                $filters = [
                    'search' => $request->get('search'),
                    'status' => $request->get('status'),
                    'vendor_id' => $request->get('vendor_id'),
                    'warehouse_id' => $request->get('warehouse_id'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'sort' => $request->get('sort'),
                    'direction' => $request->get('direction'),
                ];

                $perPage = $request->get('per_page', 20);

                return $this->service->getFilteredSupplies($filters, $perPage);
            },
            'supplies',
            'Supplies retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific supply
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function () use ($id) {
                return $this->service->get($id);
            },
            'supply',
            'Supply retrieved successfully'
        );
    }

    /**
     * The supply form, always prefilled from the purchase order behind it.
     *
     * `?purchase_order=` carries the order over from its detail page, so the
     * vendor, the destination warehouse and every outstanding line arrive
     * already answered. There is no order-less form: goods are recorded
     * against what was ordered, so without an order there is nothing to fill.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // Reading an order is what the form is made of, so a reader without
        // that permission is sent back rather than to a screen they cannot see.
        if (! $this->canSeeOrders($request)) {
            return redirect()->route('supplies.index')
                ->with('error', 'Recording a supply needs access to purchase orders.');
        }

        if (! $request->filled('purchase_order')) {
            return redirect()->route('supplies.purchase-orders')
                ->with('error', 'Pick the purchase order these goods arrived for.');
        }

        try {
            $purchaseOrder = $this->purchaseOrders->get((int) $request->input('purchase_order'));

            if (! $purchaseOrder->isReceivable()) {
                return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                    ->with('error', 'Only a sent purchase order can have a supply recorded against it.');
            }

            $prefillLines = $this->prefillLines($purchaseOrder);

            // The lines are the order's own, so the form has no product picker
            // and nothing to populate one with.
            return view('supplies.create', compact(
                'purchaseOrder',
                'prefillLines'
            ));
        } catch (DataNotFoundException $e) {
            return redirect()->route('purchase-orders.index')
                ->with('error', 'That purchase order could not be found.');
        } catch (\Exception $e) {
            \Log::error('Error loading supply creation form: '.$e->getMessage());

            // The form cannot stand up without its order, so this goes back to
            // the order list rather than rendering an empty shell.
            return redirect()->route('supplies.purchase-orders')
                ->with('error', 'Unable to load form data. Please try again later.');
        }
    }

    /**
     * The orders that have been sent to a vendor and are still waiting on
     * goods, each ready to be turned into a supply.
     */
    public function awaitingOrders(): View
    {
        try {
            $orders = $this->purchaseOrders->awaitingSupply();
        } catch (\Exception $e) {
            \Log::error('Error loading purchase orders awaiting supply: '.$e->getMessage());
            session()->now('error', 'Unable to load purchase orders. Please try again later.');

            $orders = collect();
        }

        return view('supplies.purchase-orders', compact('orders'));
    }

    public function store(StoreSupplyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            // Every delivery goes through the order's own service: it pins the
            // vendor and warehouse to the order and recounts what has been
            // received, so the order can close itself.
            $supply = $this->purchaseOrders->recordSupply(
                (int) $data['purchase_order_id'],
                $data,
                $this->service
            );

            return redirect()->route('supplies.show', $supply->id)
                ->with('success', 'Supply recorded against the purchase order.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create supply. Please try again.');
        }
    }

    public function completed(int $id): RedirectResponse
    {
        try {
            $grn = $this->service->complete($id);

            return redirect()->route('grns.show', $grn->id)
                ->with('success', 'Supply marked as completed. Receive the goods below.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function confirm(int $id): RedirectResponse
    {
        try {
            $this->service->confirm($id);

            return back()->with('success', 'Supply confirmed successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $supply = $this->service->get($id);

            return view('supplies.show', compact('supply'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading supply: '.$e->getMessage());

            return redirect()->route('supplies.index')
                ->with('error', 'Unable to load supply details. Please try again later.');
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $supply = $this->service->get($id);
            $vendors = \App\Models\Vendor::orderBy('name')->get();
            $warehouses = \App\Models\Warehouse::orderBy('name')->get();
            $products = \App\Models\Product::orderBy('name')->get();

            return view('supplies.edit', compact('supply', 'vendors', 'warehouses', 'products'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading supply for edit: '.$e->getMessage());

            return redirect()->route('supplies.index')
                ->with('error', 'Unable to load supply for editing. Please try again later.');
        }
    }

    public function update(UpdateSupplyRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->update($id, $request->validated());

            return redirect()->route('supplies.show', $id)->with('success', 'Supply updated successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to update supply. Please try again.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->service->delete($id);

            return redirect()->route('supplies.index')->with('success', 'Supply deleted successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('supplies.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('supplies.index')
                ->with('error', 'Unable to delete supply. Please try again.');
        }
    }

    /**
     * How many orders are waiting on a delivery, for the badge on the supplies
     * list. Zero for anyone who cannot see purchase orders in the first place.
     */
    private function awaitingOrdersCount(Request $request): int
    {
        if (! $this->canSeeOrders($request)) {
            return 0;
        }

        return PurchaseOrder::awaitingSupply()->count();
    }

    /**
     * The supplies routes are not permission-gated, so anything that surfaces
     * purchase order data through them has to check for itself.
     */
    private function canSeeOrders(Request $request): bool
    {
        return (bool) $request->user()?->can('purchase-orders.view');
    }

    /**
     * An order's lines as supply rows: only what is still outstanding, at the
     * cost that was agreed when the order was placed.
     *
     * These are the whole of the form's item section - it shows them and
     * submits them back, but cannot change them - so each one carries the
     * product's name and what was ordered for the reader as well.
     *
     * @return array<int, array{product_id: int, product_name: string, quantity: int, quantity_ordered: int, unit_cost: string}>
     */
    private function prefillLines(PurchaseOrder $order): array
    {
        return $order->items
            ->filter(fn ($item) => $item->outstanding() > 0)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown product',
                'quantity' => $item->outstanding(),
                'quantity_ordered' => $item->quantity_ordered,
                'unit_cost' => $item->unit_cost,
            ])
            ->values()
            ->all();
    }
}
