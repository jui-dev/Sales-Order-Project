<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class WarehouseService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Warehouse::class, 'warehouses');
    }

    public function get(int $id): Warehouse
    {
        return $this->handleServiceOperation(
            fn() => $this->findOrFail(Warehouse::class, $id, 'warehouse'),
            'warehouse',
            $id
        );
    }

    public function create(array $data): Warehouse
    {
        return $this->handleServiceOperation(
            fn() => Warehouse::create($data),
            'warehouse'
        );
    }

    public function update(int $id, array $data): Warehouse
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $warehouse = $this->findOrFail(Warehouse::class, $id, 'warehouse');
                $warehouse->update($data);
                return $warehouse;
            },
            'warehouse',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $warehouse = $this->findOrFail(Warehouse::class, $id, 'warehouse');
                $warehouse->delete();
            },
            'warehouse',
            $id
        );
    }
} 