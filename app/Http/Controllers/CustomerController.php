<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(): View
    {
        $customers = $this->service->list();
        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('customers.index')->with('success', 'Customer created.');
    }

    public function show(int $id): View
    {
        $customer = $this->service->get($id);
        return view('customers.show', compact('customer'));
    }

    public function edit(int $id): View
    {
        $customer = $this->service->get($id);
        return view('customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());
        return redirect()->route('customers.show', $id)->with('success', 'Customer updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }
} 