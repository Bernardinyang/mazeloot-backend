<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraMediaFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraMediaFeedback>
 */
class MemoraMediaFeedbackFactory extends Factory
{
    protected $model = MemoraMediaFeedback::class;

    public function definition(): array
    {
        return [
            'media_uuid' => \App\Domains\Memora\Models\MemoraMedia::factory(),
            'type' => 'text',
            'content' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
