<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state: a draft for a supplier and warehouse of its organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'sequence' => fn (array $attributes) => (PurchaseOrder::where('organization_id', $attributes['organization_id'])->max('sequence') ?? 0) + 1,
            'number' => fn (array $attributes) => PurchaseOrder::numberFor($attributes['sequence']),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'status' => 'draft',
            'expected_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the order was placed with the supplier.
     */
    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ordered',
            'ordered_at' => now(),
        ]);
    }
}
