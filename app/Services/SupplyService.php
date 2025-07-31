<?php

namespace App\Services;

use App\Models\Supply;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;

class SupplyService
{
    use HasErrorHandling;

    public function list(): Collection
    {
        return $this->handleServiceOperation(
            fn() => Supply::with(['vendor', 'warehouse', 'items', 'grn'])->latest()->get(),
            'supplies'
        );
    }

    public function get(int $id): Supply
    {
        return $this->handleServiceOperation(
            function() use ($id) {
                $supply = Supply::with(['vendor', 'warehouse', 'items', 'grn'])->find($id);
                
                if (!$supply) {
                    $this->logMissingData('supply', $id);
                    throw new \App\Exceptions\DataNotFoundException('supply', $id);
                }
                
                return $supply;
            },
            'supply',
            $id
        );
    }

    public function create(array $data): Supply
    {
        return $this->handleServiceOperation(
            fn() => Supply::create($data),
            'supply'
        );
    }

    /**
     * Create a supply together with its nested items array (same structure as validated request)
     * while automatically calculating and persisting the total_cost.
     */
    public function createWithItems(array $data): Supply
    {
        return $this->handleServiceOperation(
            function() use ($data) {
                $items = $data['products'] ?? [];
                unset($data['products']);

                // calculate total cost
                $total = collect($items)->sum(function ($itm) {
                    return ($itm['quantity'] ?? 0) * ($itm['unit_cost'] ?? 0);
                });
                $data['total_cost'] = $total;

                /** @var Supply $supply */
                $supply = Supply::create($data);

                foreach ($items as $item) {
                    $supply->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_cost'  => $item['unit_cost'],
                        'subtotal'   => $item['quantity'] * $item['unit_cost'],
                    ]);
                }

                return $supply->load(['vendor', 'warehouse', 'items']);
            },
            'supply'
        );
    }

    /**
     * Mark supply as completed.
     *
     * Stock should NOT be posted at this stage. The stock movement will be
     * handled when the corresponding GRN is marked as "posted" via the
     * GrnService. Here we simply update the status to completed and ensure
     * a GRN stub exists for the receiving team.
     */
    public function complete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $supply = $this->findOrFail(Supply::class, $id, 'supply');
                
                if ($supply->status === 'completed') {
                    throw new \Exception('Supply is already completed.');
                }

                $supply->update(['status' => 'completed']);

                // Create GRN stub if it doesn't exist
                if (!$supply->grn) {
                    $supply->grn()->create([
                        'supply_id' => $supply->id,
                        'status' => 'draft',
                        'received_date' => now(),
                    ]);
                }
            },
            'supply',
            $id
        );
    }

    /**
     * Mark supply as confirmed.
     */
    public function confirm(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $supply = $this->findOrFail(Supply::class, $id, 'supply');
                
                if ($supply->status === 'confirmed') {
                    throw new \Exception('Supply is already confirmed.');
                }

                $supply->update(['status' => 'confirmed']);
            },
            'supply',
            $id
        );
    }

    public function update(int $id, array $data): Supply
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $supply = $this->findOrFail(Supply::class, $id, 'supply');
                $supply->update($data);
                return $supply;
            },
            'supply',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $supply = $this->findOrFail(Supply::class, $id, 'supply');
                $supply->delete();
            },
            'supply',
            $id
        );
    }

    /**
     * Get filtered supplies with pagination
     */
    public function getFilteredSupplies(array $filters = [], int $perPage = 20)
    {
        return $this->getPaginatedOrEmpty(
            function() use ($filters, $perPage) {
                $query = Supply::with(['vendor', 'warehouse']);

                // Apply search filter
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('id', 'like', "%{$search}%")
                          ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                              $vendorQuery->where('name', 'like', "%{$search}%");
                          })
                          ->orWhereHas('items.product', function ($productQuery) use ($search) {
                              $productQuery->where('name', 'like', "%{$search}%");
                          });
                    });
                }

                // Apply status filter
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                // Apply vendor filter
                if (!empty($filters['vendor_id'])) {
                    $query->where('vendor_id', $filters['vendor_id']);
                }

                // Apply warehouse filter
                if (!empty($filters['warehouse_id'])) {
                    $query->where('warehouse_id', $filters['warehouse_id']);
                }

                // Apply date filters
                if (!empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }

                // Apply sorting
                $sortField = $filters['sort'] ?? 'id';
                $sortDirection = $filters['direction'] ?? 'desc';
                $query->orderBy($sortField, $sortDirection);

                return $query->paginate($perPage);
            },
            'supplies',
            $perPage,
            $filters
        );
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        return [
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                ]
            ],
            'vendor_id' => [
                'type' => 'select',
                'label' => 'Vendor',
                'options' => \App\Models\Vendor::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'warehouse_id' => [
                'type' => 'select',
                'label' => 'Warehouse',
                'options' => \App\Models\Warehouse::orderBy('name')->pluck('name', 'id')->toArray()
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'From Date',
                'placeholder' => 'Select start date'
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'To Date',
                'placeholder' => 'Select end date'
            ]
        ];
    }

    /**
     * Get sort options for the view
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created Date',
            'total_cost' => 'Total Cost',
            'status' => 'Status',
        ];
    }
} 