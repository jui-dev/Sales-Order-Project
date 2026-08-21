<?php

namespace App\Http\Controllers;

use App\Services\VendorService;
use App\Http\Requests\AssignVendorProductRequest;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorPriceListRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Exceptions\DataNotFoundException;
use App\Traits\HasApiResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class VendorController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly VendorService $service) {}

    public function index(): View
    {
        try {
            $vendors = $this->service->list();
            return view('vendors.index', compact('vendors'));
        } catch (\Exception $e) {
            // Log the error but show a user-friendly message
            \Log::error('Error loading vendors: ' . $e->getMessage());
            return view('vendors.index', ['vendors' => collect()])
                ->with('error', 'Unable to load vendors. Please try again later.');
        }
    }

    /**
     * API endpoint to get all vendors
     */
    public function apiIndex(): JsonResponse
    {
        return $this->handleApiOperation(
            function() {
                return $this->service->list();
            },
            'vendors',
            'Vendors retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific vendor
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->service->get($id);
            },
            'vendor',
            'Vendor retrieved successfully'
        );
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated());
            return redirect()->route('vendors.index')->with('success', 'Vendor created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create vendor. Please try again.');
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $vendor = $this->service->getWithPriceList($id);
            // Only products not already on this vendor's list are offerable.
            $assignableProducts = \App\Models\Product::orderBy('name')
                ->whereNotIn('id', $vendor->vendorProducts->pluck('product_id'))
                ->get(['id', 'name', 'sku']);

            return view('vendors.show', compact('vendor', 'assignableProducts'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading vendor: ' . $e->getMessage());
            return redirect()->route('vendors.index')
                ->with('error', 'Unable to load vendor details. Please try again later.');
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $vendor = $this->service->get($id);
            return view('vendors.edit', compact('vendor'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading vendor for edit: ' . $e->getMessage());
            return redirect()->route('vendors.index')
                ->with('error', 'Unable to load vendor for editing. Please try again later.');
        }
    }

    public function update(UpdateVendorRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->update($id, $request->validated());
            return redirect()->route('vendors.show', $id)->with('success', 'Vendor updated successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to update vendor. Please try again.');
        }
    }

    /* ---------------------------------------------------------------------
     | Price list
     |---------------------------------------------------------------------*/

    public function assignProduct(AssignVendorProductRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->assignProduct($id, (int) $request->validated('product_id'));

            return redirect()->route('vendors.show', $id)
                ->with('success', 'Product added. Set what this vendor charges under Product Pricing.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to add the product. Please try again.');
        }
    }

    public function updatePriceList(UpdateVendorPriceListRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->updatePriceList($id, $request->validated('rows', []));

            return redirect()->route('vendors.show', $id)
                ->with('success', 'Price list updated.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to update the price list. Please try again.');
        }
    }

    public function removeProduct(int $id, int $vendorProduct): RedirectResponse
    {
        try {
            $this->service->removeProduct($id, $vendorProduct);

            return redirect()->route('vendors.show', $id)
                ->with('success', 'Product removed from this vendor\'s price list.');
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to remove the product. Please try again.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->service->delete($id);
            return redirect()->route('vendors.index')->with('success', 'Vendor deleted successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('vendors.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('vendors.index')
                ->with('error', 'Unable to delete vendor. Please try again.');
        }
    }
} 