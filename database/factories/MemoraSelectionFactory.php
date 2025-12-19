<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraSelection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraSelection>
 */
class MemoraSelectionFactory extends Factory
{
    protected $model = MemoraSelection::class;

    public function definition(): array
    {
        return [
            'project_uuid' => \App\Domains\Memora\Models\MemoraProject::factory(),
            'name' => fake()->words(2, true),
            'status' => 'active',
            'color' => '#10B981',
            'selection_completed_at' => null,
            'auto_delete_date' => null,
        ];
    }
}
