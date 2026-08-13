<?php

namespace Database\Factories;

use App\Models\Retailer;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'from_location_id' => Warehouse::factory(),
            'from_location_type' => Warehouse::class,
            'to_location_id' => Retailer::factory(),
            'to_location_type' => Retailer::class,
            'status' => 'pending',
            'transfer_date' => now()->toDateString(),
            'notes' => null,
        ];
    }

    /**
     * A transfer whose stock has landed at the destination.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
