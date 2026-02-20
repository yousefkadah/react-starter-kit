<?php

namespace Tests\Feature\PushNotification;

use App\Http\Requests\UpdatePassFieldsRequest;
use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\User;
use App\Services\ApplePassService;
use App\Services\PassUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class ChangeMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_message_with_placeholder_is_accepted_by_request_validation(): void
    {
        $request = new UpdatePassFieldsRequest;

        $validator = validator([
            'fields' => ['primary1' => '75'],
            'change_messages' => ['primary1' => 'You now have %@ points!'],
        ], $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_multiple_change_messages_are_applied_to_multiple_fields(): void
    {
        $service = new PassUpdateService;

        $passData = [
            'primaryFields' => [
                ['key' => 'primary1', 'value' => 'old-a'],
            ],
            'secondaryFields' => [
                ['key' => 'secondary1', 'value' => 'old-b'],
            ],
        ];

        $this->invokeApplyFieldUpdates($service, $passData, [
            'primary1' => 'new-a',
            'secondary1' => 'new-b',
        ], [
            'primary1' => 'Primary changed to %@',
            'secondary1' => 'Secondary changed to %@',
        ]);

        $this->assertSame('Primary changed to %@', $passData['primaryFields'][0]['changeMessage']);
        $this->assertSame('Secondary changed to %@', $passData['secondaryFields'][0]['changeMessage']);
    }

    public function test_field_without_change_message_updates_silently(): void
    {
        $service = new PassUpdateService;

        $passData = [
            'primaryFields' => [
                ['key' => 'primary1', 'value' => 'old-a'],
            ],
        ];

        $this->invokeApplyFieldUpdates($service, $passData, [
            'primary1' => 'new-a',
        ], []);

        $this->assertSame('new-a', $passData['primaryFields'][0]['value']);
        $this->assertArrayNotHasKey('changeMessage', $passData['primaryFields'][0]);
    }

    public function test_change_message_is_included_in_regenerated_pass_json(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create([
            'apple_pass_type_id' => 'pass.com.example.test',
            'apple_team_id' => 'TEAM1234',
        ]);

        $template = PassTemplate::factory()->for($user)->create();

        /** @var Pass $pass */
        $pass = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'pass_type' => 'generic',
            'authentication_token' => 'auth-token-123456',
            'pass_data' => [
                'description' => 'My Pass',
                'primaryFields' => [
                    [
                        'key' => 'primary1',
                        'label' => 'Points',
                        'value' => '75',
                        'changeMessage' => 'You now have %@ points!',
                    ],
                ],
            ],
        ]);

        $service = ApplePassService::forUser($user);

        $json = $this->invokeBuildPassJson($service, $pass);

        $this->assertSame(
            'You now have %@ points!',
            $json['generic']['primaryFields'][0]['changeMessage']
        );
    }

    public function test_google_wallet_updates_do_not_require_custom_change_message(): void
    {
        $request = new UpdatePassFieldsRequest;

        $validator = validator([
            'fields' => ['primary1' => '75'],
        ], $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * @param  array<string, mixed>  $passData
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $changeMessages
     */
    private function invokeApplyFieldUpdates(PassUpdateService $service, array &$passData, array $fields, array $changeMessages): void
    {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('applyFieldUpdates');
        $method->setAccessible(true);
        $method->invokeArgs($service, [&$passData, $fields, $changeMessages]);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeBuildPassJson(ApplePassService $service, Pass $pass): array
    {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPassJson');
        $method->setAccessible(true);

        /** @var array<string, mixed> $json */
        $json = $method->invoke($service, $pass);

        return $json;
    }
}
