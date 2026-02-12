<?php

namespace Database\Seeders;

use App\Models\AccountTier;
use Illuminate\Database\Seeder;

class AccountTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            [
                'key' => 'Email_Verified',
                'name' => 'Email Verified',
                'description' => 'User has verified their email address.',
                'order' => 1,
            ],
            [
                'key' => 'Verified_And_Configured',
                'name' => 'Verified & Configured',
                'description' => 'User has set up at least one wallet provider (Apple or Google).',
                'order' => 2,
            ],
            [
                'key' => 'Production',
                'name' => 'Production',
                'description' => 'User has been approved for production access by admin.',
                'order' => 3,
            ],
            [
                'key' => 'Live',
                'name' => 'Live',
                'description' => 'User has created their first production pass.',
                'order' => 4,
            ],
        ];

        foreach ($tiers as $tier) {
            AccountTier::firstOrCreate(
                ['key' => $tier['key']],
                $tier
            );
        }
    }
}
