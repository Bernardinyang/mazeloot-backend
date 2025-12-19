<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraCoverStyle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraCoverStyle>
 */
class MemoraCoverStyleFactory extends Factory
{
    protected $model = MemoraCoverStyle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_default' => false,
            'config' => [],
            'preview_image_url' => null,
            'order' => 0,
        ];
    }
}
