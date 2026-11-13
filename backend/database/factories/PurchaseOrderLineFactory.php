<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    /**
     * Define the model's default state: a variant of the order's organization with a unit cost.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_variant_id' => fn (array $attributes) => ProductVariant::factory()
                ->for(Product::factory()->state(['organization_id' => PurchaseOrder::find($attributes['purchase_order_id'])->organization_id]))
                ->create()
                ->id,
            'quantity_ordered' => fake()->numberBetween(1, 100),
            'quantity_received' => 0,
            'unit_cost' => fake()->randomFloat(2, 100, 2000),
        ];
    }
}
