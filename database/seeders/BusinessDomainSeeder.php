<?php

namespace Database\Seeders;

use App\Models\BusinessDomain;
use Illuminate\Database\Seeder;

class BusinessDomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = [
            'stripe.com',
            'acme.com',
            'microsoft.com',
            'apple.com',
            'google.com',
            'amazon.com',
            'facebook.com',
            'netflix.com',
            'slack.com',
            'salesforce.com',
            'okta.com',
            'github.com',
            'gitlab.com',
            'twilio.com',
            'sendgrid.com',
            'atlassian.com',
            'jetbrains.com',
            'figma.com',
            'notion.so',
            'zoom.us',
        ];

        foreach ($domains as $domain) {
            BusinessDomain::firstOrCreate(['domain' => $domain]);
        }
    }
}
