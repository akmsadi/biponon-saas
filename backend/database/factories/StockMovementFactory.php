<?php

namespace Database\Factories;

use App\Models\SerialNumber;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state: a receipt of the serial into its current warehouse.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serial_number_id' => SerialNumber::factory(),
            'organization_id' => fn (array $attributes) => SerialNumber::find($attributes['serial_number_id'])->organization_id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => fn (array $attributes) => SerialNumber::find($attributes['serial_number_id'])->current_warehouse_id,
            'movement_type' => 'receive',
            'occurred_at' => now(),
        ];
    }
}
