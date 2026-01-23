<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EarlyAccessUser>
 */
class EarlyAccessUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'discount_percentage' => fake()->numberBetween(0, 50),
            'discount_rules' => null,
            'feature_flags' => [],
            'storage_multiplier' => fake()->randomFloat(1, 1.0, 3.0),
            'priority_support' => fake()->boolean(),
            'exclusive_badge' => true,
            'trial_extension_days' => fake()->numberBetween(0, 30),
            'custom_branding_enabled' => false,
            'release_version' => null,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
