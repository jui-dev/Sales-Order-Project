<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => 'WH-' . strtoupper($this->faker->bothify('???')),
            'address' => $this->faker->address,
            'contact_person' => $this->faker->name,
            'contact_number' => $this->faker->phoneNumber,
            'email' => $this->faker->safeEmail,
            'status' => 'active',
            'is_default' => false,
        ];
    }
} 