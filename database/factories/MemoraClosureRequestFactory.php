<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraClosureRequest>
 */
class MemoraClosureRequestFactory extends Factory
{
    protected $model = MemoraClosureRequest::class;

    public function definition(): array
    {
        return [
            'proofing_uuid' => MemoraProofing::factory(),
            'media_uuid' => MemoraMedia::factory(),
            'user_uuid' => User::factory(),
            'token' => Str::random(32),
            'todos' => ['Fix colors', 'Adjust brightness'],
            'status' => 'pending',
            'approved_at' => null,
            'rejected_at' => null,
            'approved_by_email' => null,
            'rejection_reason' => null,
            'rejected_by_email' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_email' => fake()->email(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by_email' => fake()->email(),
            'rejection_reason' => 'Not good enough',
        ]);
    }
}

