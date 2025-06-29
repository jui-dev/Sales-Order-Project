<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;

class VendorService
{
    public function list(): Collection
    {
        try {
            return Vendor::all();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function get(int $id): Vendor
    {
        return Vendor::findOrFail($id);
    }

    public function create(array $data): Vendor
    {
        return Vendor::create($data);
    }

    public function update(int $id, array $data): Vendor
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update($data);
        return $vendor;
    }

    public function delete(int $id): void
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();
    }
} 