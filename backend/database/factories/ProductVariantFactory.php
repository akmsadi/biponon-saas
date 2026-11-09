<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The organization, size and color follow the product's organization unless given explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'organization_id' => fn (array $attributes) => Product::find($attributes['product_id'])->organization_id,
            'size_id' => fn (array $attributes) => Size::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'color_id' => fn (array $attributes) => Color::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'barcode' => fake()->unique()->ean13(),
            'price' => fake()->randomFloat(2, 100, 5000),
            'is_active' => true,
        ];
    }
}
