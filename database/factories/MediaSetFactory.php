<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MediaSet;
use App\Domains\Memora\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MediaSet>
 */
class MediaSetFactory extends Factory
{
    protected $model = MediaSet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'project_uuid' => Project::factory(),
            'selection_uuid' => null,
            'proof_uuid' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'metadata' => null,
            'order' => 0,
        ];
    }
}

