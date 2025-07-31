<?php

namespace App\Http\Controllers;

use App\Services\WarehouseService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly WarehouseService $service) {}

    public function index(): View
    {
        $warehouses = $this->service->list();
        return view('stock_locations.index', compact('warehouses'));
    }

    /**
     * API endpoint to get all warehouses
     */
    public function apiIndex(): JsonResponse
    {
        return $this->handleApiOperation(
            function() {
                return $this->service->list();
            },
            'warehouses',
            'Warehouses retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific warehouse
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->service->get($id);
            },
            'warehouse',
            'Warehouse retrieved successfully'
        );
    }

    public function create(): View
    {
        return view('stock_locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);
        $this->service->create($data);
        return redirect()->route('stock-locations.index')->with('success', 'Warehouse created');
    }

    public function show(int $id): View
    {
        $warehouse = $this->service->get($id);
        return view('stock_locations.show', compact('warehouse'));
    }

    public function edit(int $id): View
    {
        $warehouse = $this->service->get($id);
        return view('stock_locations.edit', compact('warehouse'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);
        $this->service->update($id, $data);
        return redirect()->route('stock-locations.show', $id)->with('success', 'Warehouse updated');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()->route('stock-locations.index')->with('success', 'Warehouse deleted');
    }
} 