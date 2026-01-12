<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemoraMediaFeedbackFactory extends Factory
{
    protected $model = MemoraMediaFeedback::class;

    public function definition(): array
    {
        return [
            'media_uuid' => MemoraMedia::factory(),
            'parent_uuid' => null,
            'content' => fake()->sentence(),
            'type' => 'text',
            'timestamp' => null,
            'mentions' => null,
            'created_by' => null,
        ];
    }
}
