<?php

namespace App\Services;

use App\Models\Retailer;
use Illuminate\Database\Eloquent\Collection;

class RetailerService
{
    public function list(): Collection
    {
        return Retailer::all();
    }

    public function get(int $id): Retailer
    {
        return Retailer::findOrFail($id);
    }

    public function create(array $data): Retailer
    {
        return Retailer::create($data);
    }

    public function update(int $id, array $data): Retailer
    {
        $model = Retailer::findOrFail($id);
        $model->update($data);
        return $model;
    }

    public function delete(int $id): void
    {
        $model = Retailer::findOrFail($id);
        $model->delete();
    }
} 