<?php

namespace Database\Factories;

use App\Models\PassTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BulkUpdate>
 */
class BulkUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pass_template_id' => PassTemplate::factory(),
            'field_key' => fake()->randomElement(['points', 'offer', 'balance']),
            'field_value' => fake()->sentence(3),
            'filters' => null,
            'status' => fake()->randomElement(['pending', 'processing', 'completed']),
            'total_count' => fake()->numberBetween(0, 1000),
            'processed_count' => fake()->numberBetween(0, 1000),
            'failed_count' => fake()->numberBetween(0, 20),
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
