<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 1000),
            'name' => $this->faker->word(),
            'display_name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'slug' => $this->faker->slug(),
            'is_active' => true,
            'order' => 0,
        ];
    }
}
