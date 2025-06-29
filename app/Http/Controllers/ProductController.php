<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service)
    {
    }

    public function index(): View
    {
        $products = $this->service->list();
        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(int $id): View
    {
        $product = $this->service->get($id);
        return view('products.show', compact('product'));
    }

    public function edit(int $id): View
    {
        $product = $this->service->get($id);
        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());

        return redirect()->route('products.show', $id)->with('success', 'Product updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    public function transactionHistory(int $id): View
    {
        $product = $this->service->get($id);
        // This view already exists in resources/views/products/transaction-history.blade.php
        return view('products.transaction-history', compact('product'));
    }
} 