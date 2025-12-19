<?php

namespace Database\Factories;

use App\Domains\Memora\Models\Media;
use App\Domains\Memora\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'media_set_uuid' => \App\Domains\Memora\Models\MediaSet::factory(),
            'is_selected' => false,
            'selected_at' => null,
            'revision_number' => null,
            'is_completed' => false,
            'completed_at' => null,
            'original_media_uuid' => null,
            'url' => fake()->imageUrl(),
            'thumbnail_url' => null,
            'low_res_copy_url' => null,
            'type' => 'image',
            'filename' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 10000000),
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 4000),
            'order' => 0,
        ];
    }
}

