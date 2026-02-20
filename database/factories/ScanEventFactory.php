<?php

namespace Database\Factories;

use App\Models\Pass;
use App\Models\ScanEvent;
use App\Models\ScannerLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanEvent>
 */
class ScanEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ScanEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pass_id' => Pass::factory(),
            'scanner_link_id' => ScannerLink::factory(),
            'action' => fake()->randomElement(['scan', 'redeem']),
            'result' => fake()->randomElement(['success', 'invalid_signature', 'already_redeemed', 'voided', 'expired', 'not_found']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate a successful scan action.
     */
    public function scan(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'scan',
            'result' => 'success',
        ]);
    }

    /**
     * Indicate a successful redeem action.
     */
    public function redeem(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'redeem',
            'result' => 'success',
        ]);
    }
}
