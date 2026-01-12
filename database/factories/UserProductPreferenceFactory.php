<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProductPreferenceFactory extends Factory
{
    protected $model = UserProductPreference::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'product_uuid' => Product::factory(),
            'domain' => $this->faker->domainName(),
            'onboarding_completed' => false,
        ];
    }
}
