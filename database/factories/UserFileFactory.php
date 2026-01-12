<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFileFactory extends Factory
{
    protected $model = UserFile::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'url' => $this->faker->url(),
            'path' => 'uploads/'.date('Y/m/d').'/'.Str::uuid().'.jpg',
            'type' => 'image',
            'filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 10000000),
            'width' => $this->faker->numberBetween(100, 4000),
            'height' => $this->faker->numberBetween(100, 4000),
            'metadata' => [],
        ];
    }
}
