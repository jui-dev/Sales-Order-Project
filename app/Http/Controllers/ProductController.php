<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Vendor;
use App\Services\ProductService;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Exceptions\DataNotFoundException;
use App\Traits\HasApiResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly ProductService $service)
    {
    }

    public function index(Request $request): View
    {
        try {
            $filters = [
                'search' => $request->search,
                'category_id' => $request->category_id,
                'subcategory_id' => $request->subcategory_id,
                'price_min' => $request->price_min,
                'price_max' => $request->price_max,
                'stock_min' => $request->stock_min,
                'stock_max' => $request->stock_max,
                'sort' => $request->sort,
                'direction' => $request->direction,
            ];

            $products = $this->service->getFilteredProducts($filters, 20);
            $filterOptions = $this->service->getFilterOptions();
            $sortOptions = $this->service->getSortOptions();

            return view('products.index', compact('products', 'filterOptions', 'sortOptions'));
        } catch (\Exception $e) {
            \Log::error('Error loading products: ' . $e->getMessage());

            // Return empty paginated result with proper structure
            $emptyProducts = \App\Models\Product::paginate(20);
            $emptyProducts->setCollection(collect());

            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout reads the message off the session,
            // so the toast never rendered.
            session()->now('error', 'Unable to load products. Please try again later.');

            return view('products.index', [
                'products' => $emptyProducts,
                'filterOptions' => $this->service->getFilterOptions(),
                'sortOptions' => $this->service->getSortOptions()
            ]);
        }
    }

    public function create(): View
    {
        return view('products.create', [
            'mainCategories' => ProductCategory::getMainCategories(),
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        try {
            $product = $this->service->create($request->validated());
            return redirect()->route('products.show', $product->id)
                ->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create product. Please try again.');
        }
    }

    /**
     * Record that a vendor can supply this product.
     *
     * Deliberately add-only for now. Removing a vendor who already has purchase
     * orders or received stock behind them would strand those records, so that
     * needs handling properly rather than a delete button.
     *
     * What the vendor charges is NOT set here - it is set under Product
     * Pricing, so cost lives in one place.
     */
    public function addVendor(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
        ]);

        try {
            $product = $this->service->get($id);

            \App\Models\VendorProduct::firstOrCreate([
                'vendor_id' => $data['vendor_id'],
                'product_id' => $product->id,
            ]);

            // Simple mode prices a product once, whoever supplies it, so a
            // vendor added after the fact is quoted at what it already costs.
            // Without this their list would be empty and a purchase order to
            // them would open with no cost on the line.
            if (config('pricing.simple_mode', false)) {
                app(\App\Services\Pricing\SimplePricingService::class)->backfillVendor(
                    $product,
                    \App\Models\Vendor::findOrFail($data['vendor_id']),
                );

                return back()->with('success', 'Vendor added. They are quoted at this product\'s price.');
            }

            return back()->with('success', 'Vendor added. Set what they charge under Product Pricing.');
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to add that vendor. Please try again.');
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $product = $this->service->get($id);

            // Load stock balances for the product with proper location relationships
            $product->load(['stockBalances.location']);

            $transactionHistory = $this->service->transactionHistory($product);
            $stockAnalysis = $this->service->stockAnalysis($product);

            // Who can supply this product, and who else could be added. What
            // each of them charges is set under Catalog > Product Pricing, so
            // there is one place money is decided.
            $product->load('vendors');
            $assignableVendors = \App\Models\Vendor::whereNotIn('id', $product->vendors->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name']);

            return view('products.show', compact(
                'product', 'transactionHistory', 'stockAnalysis', 'assignableVendors'
            ));
        } catch (DataNotFoundException $e) {
            return redirect()->route('products.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading product: ' . $e->getMessage());
            return redirect()->route('products.index')
                ->with('error', 'Unable to load product details. Please try again later.');
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $product = $this->service->get($id);

            // A product stores a single category_id which may point at either a
            // main category or a subcategory. Split it back into the two selects
            // so both render with the correct value preselected.
            $parentId = $product->category?->parent_id;

            return view('products.edit', [
                'product' => $product,
                'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
                'assignedVendorIds' => $product->vendors()->pluck('vendors.id')->all(),
                'mainCategories' => ProductCategory::getMainCategories(),
                'subcategories' => $parentId
                    ? ProductCategory::getSubcategories($parentId)
                    : collect(),
                'selectedCategoryId' => $parentId ?? $product->category_id,
                'selectedSubcategoryId' => $parentId ? $product->category_id : null,
            ]);
        } catch (DataNotFoundException $e) {
            return redirect()->route('products.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('products.index')
                ->with('error', 'Unable to load product for editing. Please try again later.');
        }
    }

    public function update(UpdateProductRequest $request, int $id): RedirectResponse
    {
        try {
            $product = $this->service->update($id, $request->validated());
            return redirect()->route('products.show', $product->id)
                ->with('success', 'Product updated successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('products.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to update product. Please try again.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->service->delete($id);
            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('products.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('products.index')
                ->with('error', 'Unable to delete product. Please try again.');
        }
    }

    /**
     * Get subcategories for a selected category (AJAX endpoint)
     */
    public function getSubcategories(Request $request): JsonResponse
    {
        try {
            $categoryId = $request->input('category_id');

            \Log::info('getSubcategories called with category_id: ' . $categoryId);
            \Log::info('Request headers: ' . json_encode($request->headers->all()));
            \Log::info('Request method: ' . $request->method());
            \Log::info('Request URL: ' . $request->url());

            if (!$categoryId) {
                \Log::info('No category_id provided, returning empty options');
                return response()->json(['options' => ['' => 'All Subcategories']]);
            }

            $subcategories = \App\Models\ProductCategory::getSubcategories($categoryId);
            $options = ['' => 'All Subcategories'];

            foreach ($subcategories as $subcategory) {
                $options[$subcategory->id] = $subcategory->name;
            }

            \Log::info('Returning subcategories for category ' . $categoryId . ': ' . count($options) . ' options');
            \Log::info('Response data: ' . json_encode(['options' => $options]));

            // Create response manually to bypass any trait formatting
            $response = new \Illuminate\Http\JsonResponse(['options' => $options], 200);
            $response->header('Content-Type', 'application/json');
            $response->header('Cache-Control', 'no-cache');

            return $response;
        } catch (\Exception $e) {
            \Log::error('Error in getSubcategories: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to load subcategories'], 500);
        }
    }

    /**
     * API endpoint for getting products (for AJAX requests)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'search' => $request->search,
                    'price_min' => $request->price_min,
                    'price_max' => $request->price_max,
                    'stock_min' => $request->stock_min,
                    'stock_max' => $request->stock_max,
                    'sort' => $request->sort,
                    'direction' => $request->direction,
                ];

                $perPage = $request->get('per_page', 20);
                return $this->service->getFilteredProducts($filters, $perPage);
            },
            'products',
            'Products retrieved successfully'
        );
    }

    /**
     * API endpoint for getting a single product
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->service->get($id);
            },
            'product',
            'Product retrieved successfully'
        );
    }

    /**
     * Recalculate all product stocks
     */
    public function recalculateStocks(): JsonResponse
    {
        return $this->handleApiOperation(
            function() {
                return $this->service->recalculateAllProductStocks();
            },
            'product stocks',
            'Product stocks recalculated successfully'
        );
    }

    /**
     * Show stock analysis for a product
     */
    public function stockAnalysis(int $id): View|RedirectResponse
    {
        try {
            $product = $this->service->get($id);
            $stockData = $this->service->stockAnalysis($product);

            // Pass stockData as expected by the view
            return view('products.stock-analysis', compact('stockData'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('products.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading stock analysis: ' . $e->getMessage());
            return redirect()->route('products.index')
                ->with('error', 'Unable to load stock analysis. Please try again later.');
        }
    }
}
