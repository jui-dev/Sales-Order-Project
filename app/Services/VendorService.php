<?php

namespace App\Services;

use App\Models\Vendor;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class VendorService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Vendor::class, 'vendors');
    }

    public function get(int $id): Vendor
    {
        return $this->handleServiceOperation(
            fn() => $this->findOrFail(Vendor::class, $id, 'vendor'),
            'vendor',
            $id
        );
    }

    public function create(array $data): Vendor
    {
        return $this->handleServiceOperation(
            fn() => Vendor::create($data),
            'vendor'
        );
    }

    public function update(int $id, array $data): Vendor
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $vendor = $this->findOrFail(Vendor::class, $id, 'vendor');
                $vendor->update($data);
                return $vendor;
            },
            'vendor',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $vendor = $this->findOrFail(Vendor::class, $id, 'vendor');
                $vendor->delete();
            },
            'vendor',
            $id
        );
    }
} 