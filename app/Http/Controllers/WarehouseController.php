<?php

namespace App\Http\Controllers;

use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $service) {}

    public function index(): View
    {
        $warehouses = $this->service->list();
        return view('stock_locations.index', compact('warehouses'));
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