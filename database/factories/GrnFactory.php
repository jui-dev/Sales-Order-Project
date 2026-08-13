<?php

namespace Database\Factories;

use App\Models\Grn;
use App\Models\Supply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grn>
 */
class GrnFactory extends Factory
{
    protected $model = Grn::class;

    public function definition(): array
    {
        return [
            'supply_id' => Supply::factory(),
            'received_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }

    /**
     * A posted GRN — the point at which stock actually enters the warehouse.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
        ]);
    }
}
