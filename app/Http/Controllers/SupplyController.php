<?php

namespace App\Http\Controllers;

use App\Exceptions\DataNotFoundException;
use App\Http\Requests\StoreSupplyRequest;
use App\Http\Requests\UpdateSupplyRequest;
use App\Models\Supply;
use App\Services\SupplyService;
use App\Traits\HasApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly SupplyService $service) {}

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

            return view('supplies.index', compact('supplies', 'filterOptions', 'sortOptions'));
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

    public function create(): View
    {
        try {
            $vendors = \App\Models\Vendor::orderBy('name')->get();
            $warehouses = \App\Models\Warehouse::orderBy('name')->get();
            $products = \App\Models\Product::with('category')->orderBy('name')->get();
            $categories = \App\Models\ProductCategory::getMainCategories();

            return view('supplies.create', compact('vendors', 'warehouses', 'products', 'categories'));
        } catch (\Exception $e) {
            \Log::error('Error loading supply creation form: '.$e->getMessage());

            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout reads the message off the session,
            // so the toast never rendered.
            session()->now('error', 'Unable to load form data. Please try again later.');

            return view('supplies.create', [
                'vendors' => collect(),
                'warehouses' => collect(),
                'products' => collect(),
                'categories' => collect(),
            ]);
        }
    }

    public function store(StoreSupplyRequest $request): RedirectResponse
    {
        try {
            $this->service->createWithItems($request->validated());

            return redirect()->route('supplies.index')->with('success', 'Supply created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create supply. Please try again.');
        }
    }

    public function completed(int $id): RedirectResponse
    {
        try {
            $this->service->complete($id);

            return back()->with('success', 'Supply marked as completed successfully.');
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
}
