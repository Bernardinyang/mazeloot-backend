<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraProject>
 */
class ProjectFactory extends Factory
{
    protected $model = MemoraProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
            'has_selections' => false,
            'has_proofing' => false,
            'has_collections' => false,
            'settings' => [],
        ];
    }
}
