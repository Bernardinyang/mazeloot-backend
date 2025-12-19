<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraPreset>
 */
class MemoraPresetFactory extends Factory
{
    protected $model = MemoraPreset::class;

    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'name' => fake()->words(2, true),
            'is_selected' => false,
            'collection_tags' => null,
            'photo_sets' => null,
            'email_registration' => false,
            'gallery_assist' => false,
            'slideshow' => true,
            'social_sharing' => true,
            'language' => 'en',
        ];
    }
}
