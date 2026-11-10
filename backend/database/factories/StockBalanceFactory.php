<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
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
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
