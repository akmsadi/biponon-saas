<?php

namespace Database\Factories;

use App\Models\AccessRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRequest>
 */
class AccessRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'message' => fake()->optional()->sentence(),
            'language' => 'en',
            'status' => 'new',
        ];
    }

    public function contacted(): static
    {
        return $this->state(['status' => 'contacted']);
    }
}
