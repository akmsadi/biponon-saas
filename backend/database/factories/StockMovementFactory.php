<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state: a receipt of the variant into a warehouse of its organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'organization_id' => fn (array $attributes) => ProductVariant::find($attributes['product_variant_id'])->organization_id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'quantity' => fake()->numberBetween(1, 50),
            'movement_type' => 'receive',
            'occurred_at' => now(),
        ];
    }
}
