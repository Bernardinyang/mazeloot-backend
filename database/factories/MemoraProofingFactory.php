<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraProofing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraProofing>
 */
class MemoraProofingFactory extends Factory
{
    protected $model = MemoraProofing::class;

    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'project_uuid' => \App\Domains\Memora\Models\MemoraProject::factory(),
            'name' => fake()->words(2, true),
            'status' => 'draft',
            'color' => '#F59E0B',
            'max_revisions' => 5,
            'current_revision' => 0,
            'completed_at' => null,
        ];
    }
}
