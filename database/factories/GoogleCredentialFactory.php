<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoogleCredential>
 */
class GoogleCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $projectId = fake()->slug(2);
        $clientEmail = "passkit-service@{$projectId}.iam.gserviceaccount.com";

        // Mock RSA private key (not a real key, for testing only)
        $privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7W8w+3cZqhd8C
fwbcJVWqMNY6fqJPBr5SJDq0lkfvvA8zVcnVz0FMJy1lZJ9xE5zDpBzMDx6R8PlM
PEM;

        return [
            'user_id' => User::factory(),
            'issuer_id' => str(fake()->word())->slug()->value(),
            'private_key' => encrypt($privateKey),
            'project_id' => $projectId,
            'last_rotated_at' => now(),
        ];
    }

    /**
     * Create a credential that hasn't been rotated recently.
     */
    public function unrotated(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_rotated_at' => now()->subMonths(4),
        ]);
    }

    /**
     * Create a credential with no rotation history.
     */
    public function neverRotated(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_rotated_at' => null,
        ]);
    }
}
