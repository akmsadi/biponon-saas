<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    /**
     * Define the model's default state. Use withToken() when a test needs the plain token for the link.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'member',
            'token' => OrganizationInvitation::hashToken(Str::random(48)),
            'invited_by' => null,
            'expires_at' => now()->addDays(OrganizationInvitation::VALID_FOR_DAYS),
            'accepted_at' => null,
        ];
    }

    /**
     * Store the hash of the given plain token.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token' => OrganizationInvitation::hashToken($token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
