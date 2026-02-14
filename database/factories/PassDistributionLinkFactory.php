<?php

namespace Database\Factories;

use App\Models\Pass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PassDistributionLink>
 */
class PassDistributionLinkFactory extends Factory
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
            'slug' => Str::uuid(),
            'status' => 'active',
            'accessed_count' => 0,
            'last_accessed_at' => null,
        ];
    }

    /**
     * Mark the link as disabled.
     */
    public function disabled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'disabled',
            ];
        });
    }

    /**
     * Mark the link as accessed multiple times.
     */
    public function accessed(int $count = 5): self
    {
        return $this->state(function (array $attributes) use ($count) {
            return [
                'accessed_count' => $count,
                'last_accessed_at' => now()->subHours(rand(1, 24)),
            ];
        });
    }
}
