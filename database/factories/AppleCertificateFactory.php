<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppleCertificate>
 */
class AppleCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $validFrom = fake()->dateTimeBetween('-6 months', '-3 months');
        $expiryDate = fake()->dateTimeBetween('+3 months', '+12 months');

        return [
            'user_id' => User::factory(),
            'path' => 'wwdr-' . fake()->uuid() . '.cer',
            'password' => encrypt(fake()->password()),
            'valid_from' => $validFrom,
            'expiry_date' => $expiryDate,
            'expiry_notified_30_days' => false,
            'expiry_notified_7_days' => false,
            'expiry_notified_0_days' => false,
        ];
    }

    /**
     * Create a certificate expiring in N days.
     */
    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->addDays($days),
            'valid_from' => now()->subMonths(6),
        ]);
    }

    /**
     * Create an already expired certificate.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->subDays(30),
            'valid_from' => now()->subMonths(12),
        ]);
    }

    /**
     * Create a valid certificate (not expiring soon).
     */
    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->addYear(),
            'valid_from' => now()->subMonths(6),
        ]);
    }
}
