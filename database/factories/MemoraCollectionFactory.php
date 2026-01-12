<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraCollection>
 */
class MemoraCollectionFactory extends Factory
{
    protected $model = MemoraCollection::class;

    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'project_uuid' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'color' => '#8B5CF6',
        ];
    }
}
