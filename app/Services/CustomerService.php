<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function list(): Collection
    {
        try {
            return Customer::all();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function get(int $id): Customer
    {
        return Customer::findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);
        return $customer;
    }

    public function delete(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
    }
} 