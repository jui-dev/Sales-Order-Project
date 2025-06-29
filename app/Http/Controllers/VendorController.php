<?php

namespace App\Http\Controllers;

use App\Services\VendorService;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly VendorService $service) {}

    public function index(): View
    {
        $vendors = $this->service->list();
        return view('vendors.index', compact('vendors'));
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('vendors.index')->with('success', 'Vendor created.');
    }

    public function show(int $id): View
    {
        $vendor = $this->service->get($id);
        return view('vendors.show', compact('vendor'));
    }

    public function edit(int $id): View
    {
        $vendor = $this->service->get($id);
        return view('vendors.edit', compact('vendor'));
    }

    public function update(UpdateVendorRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());
        return redirect()->route('vendors.show', $id)->with('success', 'Vendor updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()->route('vendors.index')->with('success', 'Vendor deleted.');
    }
} 