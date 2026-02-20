<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceRegistration>
 */
class DeviceRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_library_identifier' => fake()->uuid(),
            'push_token' => fake()->lexify(str_repeat('?', 64)),
            'pass_type_identifier' => 'pass.com.example.loyalty',
            'serial_number' => fake()->uuid(),
            'user_id' => User::factory(),
            'is_active' => true,
        ];
    }
}
