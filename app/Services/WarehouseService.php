<?php

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class WarehouseService
{
    public function list(): Collection
    {
        return Warehouse::all();
    }

    public function get(int $id): Warehouse
    {
        return Warehouse::findOrFail($id);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(int $id, array $data): Warehouse
    {
        $model = Warehouse::findOrFail($id);
        $model->update($data);
        return $model;
    }

    public function delete(int $id): void
    {
        $model = Warehouse::findOrFail($id);
        $model->delete();
    }
} 