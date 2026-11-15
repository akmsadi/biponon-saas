<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderLine>
 */
class SalesOrderLineFactory extends Factory
{
    /**
     * Define the model's default state: a variant of the order's organization with a unit price.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_variant_id' => fn (array $attributes) => ProductVariant::factory()
                ->for(Product::factory()->state(['organization_id' => SalesOrder::find($attributes['sales_order_id'])->organization_id]))
                ->create()
                ->id,
            'quantity_ordered' => fake()->numberBetween(1, 100),
            'quantity_dispatched' => 0,
            'unit_price' => fake()->randomFloat(2, 200, 5000),
        ];
    }
}
