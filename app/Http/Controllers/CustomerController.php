<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Exceptions\DataNotFoundException;
use App\Traits\HasApiResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(): View
    {
        try {
            $customers = $this->service->list();
            $filterOptions = $this->service->getFilterOptions();
            $sortOptions = $this->service->getSortOptions();
            return view('customers.index', compact('customers', 'filterOptions', 'sortOptions'));
        } catch (\Exception $e) {
            // Log the error but show a user-friendly message
            \Log::error('Error loading customers: ' . $e->getMessage());
            return view('customers.index', [
                'customers' => collect(),
                'filterOptions' => $this->service->getFilterOptions(),
                'sortOptions' => $this->service->getSortOptions()
            ])->with('error', 'Unable to load customers. Please try again later.');
        }
    }

    /**
     * API endpoint to get all customers
     */
    public function apiIndex(): JsonResponse
    {
        return $this->handleApiOperation(
            function() {
                return $this->service->list();
            },
            'customers',
            'Customers retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific customer
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->service->get($id);
            },
            'customer',
            'Customer retrieved successfully'
        );
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated());
            return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to create customer. Please try again.');
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        try {
            $customer = $this->service->get($id);
            return view('customers.show', compact('customer'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('customers.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading customer: ' . $e->getMessage());
            return redirect()->route('customers.index')
                ->with('error', 'Unable to load customer details. Please try again later.');
        }
    }

    public function edit(int $id): View|RedirectResponse
    {
        try {
            $customer = $this->service->get($id);
            return view('customers.edit', compact('customer'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('customers.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error loading customer for edit: ' . $e->getMessage());
            return redirect()->route('customers.index')
                ->with('error', 'Unable to load customer for editing. Please try again later.');
        }
    }

    public function update(UpdateCustomerRequest $request, int $id): RedirectResponse
    {
        try {
            $this->service->update($id, $request->validated());
            return redirect()->route('customers.show', $id)->with('success', 'Customer updated successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('customers.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Unable to update customer. Please try again.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->service->delete($id);
            return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
        } catch (DataNotFoundException $e) {
            return redirect()->route('customers.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('customers.index')
                ->with('error', 'Unable to delete customer. Please try again.');
        }
    }
} 