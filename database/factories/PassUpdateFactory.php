<?php

namespace Database\Factories;

use App\Models\Pass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PassUpdate>
 */
class PassUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'user_id' => User::factory(),
            'bulk_update_id' => null,
            'source' => fake()->randomElement(['dashboard', 'api', 'bulk', 'system']),
            'fields_changed' => [
                'points' => ['old' => 50, 'new' => 75],
            ],
            'apple_delivery_status' => fake()->randomElement(['pending', 'sent', 'delivered']),
            'google_delivery_status' => fake()->randomElement(['pending', 'sent', 'delivered']),
            'apple_devices_notified' => fake()->numberBetween(0, 3),
            'google_updated' => fake()->boolean(),
            'error_message' => null,
        ];
    }
}
