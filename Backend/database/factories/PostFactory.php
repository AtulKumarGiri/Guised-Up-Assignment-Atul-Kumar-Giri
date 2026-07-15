<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'text' => $this->faker->paragraph(),
            'image_url' => null,
            'has_filter' => false,
            'authenticity_score' => $this->faker->randomFloat(2, 0, 1),
        ];
    }
}
