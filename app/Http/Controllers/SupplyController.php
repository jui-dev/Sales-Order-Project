<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplyRequest;
use App\Models\Supply;
use App\Services\SupplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(private readonly SupplyService $service)
    {
    }

    public function index(): View
    {
        $supplies = $this->service->list();
        return view('supplies.index', compact('supplies'));
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
        $this->service->complete($id);
        return back()->with('success', 'Supply marked as completed and GRN generated.');
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
} 