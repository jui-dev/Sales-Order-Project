<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductService;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = Product::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter functionality
        if ($request->filled('price_min')) {
            $query->where('selling_price', '>=', $request->price_min);
        }

        if ($request->filled('price_max')) {
            $query->where('selling_price', '<=', $request->price_max);
        }

        if ($request->filled('stock_min')) {
            $query->where('available_stocks', '>=', $request->stock_min);
        }

        if ($request->filled('stock_max')) {
            $query->where('available_stocks', '<=', $request->stock_max);
        }

        // Sort functionality
        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'name':
                $query->orderBy('name', $direction);
                break;
            case 'price':
                $query->orderBy('selling_price', $direction);
                break;
            case 'stock':
                $query->orderBy('available_stocks', $direction);
                break;
            case 'created':
                $query->orderBy('created_at', $direction);
                break;
            default:
                $query->orderBy('id', $direction);
        }

        $products = $query->paginate(20)->withQueryString();

        // Get filter options for the view
        $filterOptions = [
            'price_min' => [
                'type' => 'text',
                'label' => 'Min Price',
                'placeholder' => 'Enter minimum price'
            ],
            'price_max' => [
                'type' => 'text',
                'label' => 'Max Price',
                'placeholder' => 'Enter maximum price'
            ],
            'stock_min' => [
                'type' => 'text',
                'label' => 'Min Stock',
                'placeholder' => 'Enter minimum stock'
            ],
            'stock_max' => [
                'type' => 'text',
                'label' => 'Max Stock',
                'placeholder' => 'Enter maximum stock'
            ]
        ];

        $sortOptions = [
            'id' => 'ID',
            'name' => 'Name',
            'price' => 'Price',
            'stock' => 'Stock',
            'created' => 'Created Date'
        ];

        return view('products.index', compact('products', 'filterOptions', 'sortOptions'));
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