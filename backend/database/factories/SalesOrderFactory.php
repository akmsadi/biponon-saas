<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * Define the model's default state: a draft for a customer and warehouse of its organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'sequence' => fn (array $attributes) => (SalesOrder::where('organization_id', $attributes['organization_id'])->max('sequence') ?? 0) + 1,
            'number' => fn (array $attributes) => SalesOrder::numberFor($attributes['sequence']),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'status' => 'draft',
            'deliver_by' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the order was confirmed with the customer.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }
}
