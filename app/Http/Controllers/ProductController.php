<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
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

            return view('products.index', [
                'products' => $emptyProducts,
                'filterOptions' => $this->service->getFilterOptions(),
                'sortOptions' => $this->service->getSortOptions()
            ])->with('error', 'Unable to load products. Please try again later.');
        }
    }

    public function create(): View
    {
        return view('products.create', [
            'mainCategories' => ProductCategory::getMainCategories(),
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

    public function show(int $id): View|RedirectResponse
    {
        try {
            $product = $this->service->get($id);

            // Load stock balances for the product with proper location relationships
            $product->load(['stockBalances.location']);

            $transactionHistory = $this->service->transactionHistory($product);
            $stockAnalysis = $this->service->stockAnalysis($product);

            return view('products.show', compact('product', 'transactionHistory', 'stockAnalysis'));
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
