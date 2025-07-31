<?php

namespace App\Services;

use App\Models\Retailer;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class RetailerService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Retailer::class, 'retailers');
    }

    public function get(int $id): Retailer
    {
        return $this->handleServiceOperation(
            fn() => $this->findOrFail(Retailer::class, $id, 'retailer'),
            'retailer',
            $id
        );
    }

    public function create(array $data): Retailer
    {
        return $this->handleServiceOperation(
            fn() => Retailer::create($data),
            'retailer'
        );
    }

    public function update(int $id, array $data): Retailer
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $retailer = $this->findOrFail(Retailer::class, $id, 'retailer');
                $retailer->update($data);
                return $retailer;
            },
            'retailer',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $retailer = $this->findOrFail(Retailer::class, $id, 'retailer');
                $retailer->delete();
            },
            'retailer',
            $id
        );
    }
} 