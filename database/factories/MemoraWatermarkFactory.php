<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraWatermark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraWatermark>
 */
class MemoraWatermarkFactory extends Factory
{
    protected $model = MemoraWatermark::class;

    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'name' => fake()->words(2, true),
            'type' => 'text',
            'image_file_uuid' => null,
            'text' => fake()->company(),
            'font_family' => 'Arial',
            'font_style' => 'normal',
            'font_color' => '#000000',
            'background_color' => null,
            'line_height' => 1.5,
            'letter_spacing' => 0,
            'padding' => 5,
            'text_transform' => 'none',
            'border_radius' => 0,
            'border_width' => 0,
            'border_color' => null,
            'border_style' => 'none',
            'scale' => 50,
            'opacity' => 80,
            'position' => 'bottom-right',
        ];
    }
}
