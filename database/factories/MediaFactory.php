<?php

namespace Database\Factories;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Memora\Models\MemoraMedia>
 */
class MediaFactory extends Factory
{
    protected $model = MemoraMedia::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => \App\Models\User::factory(),
            'is_selected' => false,
            'selected_at' => null,
            'revision_number' => null,
            'is_completed' => false,
            'completed_at' => null,
            'original_media_uuid' => null,
            'url' => fake()->imageUrl(),
            'type' => 'image',
            'filename' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 10000000),
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 4000),
            'order' => 0,
        ];
    }

    /**
     * Configure the factory to ensure media_set_uuid is always set.
     */
    public function configure(): static
    {
        return parent::configure()->afterMaking(function (MemoraMedia $media) {
            $mediaSetUuid = $media->getAttributeValue('media_set_uuid');
            
            if (!$mediaSetUuid || !is_string($mediaSetUuid) || strlen($mediaSetUuid) !== 36) {
                $userId = $media->getAttributeValue('user_uuid');
                
                if ($userId instanceof Factory) {
                    $user = \App\Models\User::factory()->create();
                    $userId = $user->uuid;
                } elseif (empty($userId) || !is_string($userId)) {
                    $user = \App\Models\User::factory()->create();
                    $userId = $user->uuid;
                }
                
                $set = MemoraMediaSet::factory()->create([
                    'user_uuid' => $userId,
                ]);
                
                $media->fill(['media_set_uuid' => $set->uuid]);
            }
        });
    }
}

