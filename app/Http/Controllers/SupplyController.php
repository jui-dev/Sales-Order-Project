<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplyRequest;
use App\Http\Requests\UpdateSupplyRequest;
use App\Models\Supply;
use App\Services\SupplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(private readonly SupplyService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = Supply::with(['vendor', 'warehouse', 'items.product']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function($vendor) use ($search) {
                      $vendor->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('items.product', function($product) use ($search) {
                      $product->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter functionality
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('supply_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('supply_date', '<=', $request->date_to);
        }

        // Sort functionality
        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'vendor':
                $query->orderBy('vendor_id', $direction);
                break;
            case 'date':
                $query->orderBy('supply_date', $direction);
                break;
            case 'total_cost':
                $query->orderBy('total_cost', $direction);
                break;
            case 'status':
                $query->orderBy('status', $direction);
                break;
            default:
                $query->orderBy('id', $direction);
        }

        $supplies = $query->paginate(20)->withQueryString();

        // Get filter options for the view
        $filterOptions = [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed'
                ]
            ],
            'vendor_id' => [
                'type' => 'select',
                'label' => 'Vendor',
                'options' => \App\Models\Vendor::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'warehouse_id' => [
                'type' => 'select',
                'label' => 'Warehouse',
                'options' => \App\Models\Warehouse::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'Date From'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'Date To'
            ]
        ];

        $sortOptions = [
            'id' => 'ID',
            'vendor' => 'Vendor',
            'date' => 'Supply Date',
            'total_cost' => 'Total Cost',
            'status' => 'Status'
        ];

        return view('supplies.index', compact('supplies', 'filterOptions', 'sortOptions'));
    }

    public function create(): View
    {
        // Use existing logic that route create uses to fetch dependencies
        $vendors    = \App\Models\Vendor::orderBy('name')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();
        $products   = \App\Models\Product::select(['id', 'name', 'available_stocks'])->orderBy('name')->get();
        return view('supplies.create', compact('vendors', 'warehouses', 'products'));
    }

    public function store(StoreSupplyRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->service->createWithItems($validated);
        return redirect()->route('supplies.index')->with('success', 'Supply recorded successfully.');
    }

    public function completed(int $id): RedirectResponse
    {
        $supply = $this->service->complete($id);

        // If GRN was (or already) generated, redirect directly to it
        if ($supply->grn) {
            return redirect()->route('grns.show', $supply->grn)
                ->with('success', 'Supply marked as completed. GRN generated.');
        }

        // Fallback – should not normally happen
        return back()->with('success', 'Supply marked as completed.');
    }

    public function confirm(int $id): RedirectResponse
    {
        $this->service->confirm($id);
        return back()->with('success', 'Supply confirmed and GRN generated.');
    }

    public function show(int $id): View
    {
        $supply = $this->service->get($id);
        return view('supplies.show', compact('supply'));
    }

    public function edit(int $id): View
    {
        $supply = $this->service->get($id);
        $vendors = \App\Models\Vendor::orderBy('name')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();
        $products = \App\Models\Product::select(['id', 'name', 'available_stocks'])->orderBy('name')->get();
        
        return view('supplies.edit', compact('supply', 'vendors', 'warehouses', 'products'));
    }

    public function update(UpdateSupplyRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $this->service->updateWithItems($id, $validated);
        return redirect()->route('supplies.show', $id)->with('success', 'Supply updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()->route('supplies.index')->with('success', 'Supply deleted successfully.');
    }
} 