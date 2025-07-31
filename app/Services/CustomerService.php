<?php

namespace App\Services;

use App\Models\Customer;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Customer::class, 'customers');
    }

    public function get(int $id): Customer
    {
        return $this->handleServiceOperation(
            fn() => $this->findOrFail(Customer::class, $id, 'customer'),
            'customer',
            $id
        );
    }

    public function create(array $data): Customer
    {
        return $this->handleServiceOperation(
            fn() => Customer::create($data),
            'customer'
        );
    }

    public function update(int $id, array $data): Customer
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $customer = $this->findOrFail(Customer::class, $id, 'customer');
                $customer->update($data);
                return $customer;
            },
            'customer',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $customer = $this->findOrFail(Customer::class, $id, 'customer');
                $customer->delete();
            },
            'customer',
            $id
        );
    }

    public function getFilterOptions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'label' => 'Name',
                'placeholder' => 'Search by customer name...'
            ],
            'email' => [
                'type' => 'text',
                'label' => 'Email',
                'placeholder' => 'Search by email...'
            ],
            'phone' => [
                'type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Search by phone...'
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'Created From',
                'placeholder' => 'Select start date...'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'Created To',
                'placeholder' => 'Select end date...'
            ]
        ];
    }

    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'created_at' => 'Created Date'
        ];
    }
} 