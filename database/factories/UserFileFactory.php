<?php

namespace Database\Factories;

use App\Domains\Memora\Enums\MediaTypeEnum;
use App\Models\UserFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserFile>
 */
class UserFileFactory extends Factory
{
    protected $model = UserFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'url' => fake()->url(),
            'path' => 'uploads/'.fake()->uuid().'.jpg',
            'type' => MediaTypeEnum::IMAGE,
            'filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 10000000),
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 4000),
            'metadata' => [],
        ];
    }
}
