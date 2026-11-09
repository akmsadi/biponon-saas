<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerialNumber>
 */
class SerialNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The organization and warehouse follow the variant's organization unless given explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'organization_id' => fn (array $attributes) => ProductVariant::find($attributes['product_variant_id'])->organization_id,
            'current_warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'status' => 'in_stock',
            'received_at' => now(),
        ];
    }

    /**
     * Indicate that the unit has left stock.
     */
    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_warehouse_id' => null,
            'status' => 'dispatched',
        ]);
    }
}
