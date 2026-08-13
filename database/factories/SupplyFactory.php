<?php

namespace Database\Factories;

use App\Models\Supply;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supply>
 */
class SupplyFactory extends Factory
{
    protected $model = Supply::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'pending',
            'supply_date' => now()->toDateString(),
            'total_cost' => 0,
            'notes' => null,
        ];
    }

    /**
     * A supply whose goods have been received.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
