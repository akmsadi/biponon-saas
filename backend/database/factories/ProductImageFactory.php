<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'organization_id' => fn (array $attributes) => Product::find($attributes['product_id'])->organization_id,
            'path' => fn (array $attributes) => 'products/test/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }
}
