<?php

namespace App\Services;

use App\Events\PassUpdatedEvent;
use App\Jobs\SendApplePushNotificationJob;
use App\Jobs\UpdateGoogleWalletObjectJob;
use App\Models\Pass;
use App\Models\PassUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PassUpdateService
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $changeMessages
     */
    public function updatePassFields(Pass $pass, array $fields, ?User $initiator = null, string $source = 'dashboard', array $changeMessages = [], ?int $bulkUpdateId = null): PassUpdate
    {
        if ($pass->isVoided()) {
            throw new RuntimeException('Voided passes cannot be updated.');
        }

        $this->validateFieldsAgainstTemplate($pass, $fields);

        return DB::transaction(function () use ($pass, $fields, $initiator, $source, $changeMessages, $bulkUpdateId): PassUpdate {
            $passData = is_array($pass->pass_data) ? $pass->pass_data : [];
            $fieldsChanged = $this->applyFieldUpdates($passData, $fields, $changeMessages);

            $encoded = json_encode($passData);
            if ($encoded !== false && strlen($encoded) > 10240) {
                throw new RuntimeException('Pass data exceeds 10KB limit after update.');
            }

            $pass->update(['pass_data' => $passData]);

            $appleStatus = 'skipped';
            $googleStatus = 'skipped';

            $platforms = $this->extractPlatforms($pass);

            if (in_array('apple', $platforms, true)) {
                ApplePassService::forUser($pass->user)->generate($pass);
                $appleStatus = 'pending';
            }

            if (in_array('google', $platforms, true) && is_string($pass->google_object_id) && $pass->google_object_id !== '') {
                $googleStatus = 'pending';
            }

            $passUpdate = PassUpdate::query()->create([
                'pass_id' => $pass->id,
                'user_id' => $initiator?->id,
                'bulk_update_id' => $bulkUpdateId,
                'source' => $source,
                'fields_changed' => $fieldsChanged,
                'apple_delivery_status' => $appleStatus,
                'google_delivery_status' => $googleStatus,
                'apple_devices_notified' => 0,
                'google_updated' => $googleStatus !== 'skipped',
            ]);

            if (in_array('apple', $platforms, true)) {
                $pushTokens = $pass->deviceRegistrations()
                    ->active()
                    ->pluck('push_token')
                    ->all();

                if ($pushTokens === []) {
                    $passUpdate->forceFill([
                        'apple_delivery_status' => 'skipped',
                        'apple_devices_notified' => 0,
                    ])->save();
                } else {
                    foreach ($pushTokens as $pushToken) {
                        SendApplePushNotificationJob::dispatch($pass->id, $passUpdate->id, (string) $pushToken);
                    }

                    $passUpdate->forceFill([
                        'apple_delivery_status' => 'sent',
                        'apple_devices_notified' => count($pushTokens),
                    ])->save();
                }
            }

            if (
                in_array('google', $platforms, true)
                && is_string($pass->google_object_id)
                && $pass->google_object_id !== ''
            ) {
                UpdateGoogleWalletObjectJob::dispatch($pass->id, $passUpdate->id);

                $passUpdate->forceFill([
                    'google_delivery_status' => 'sent',
                ])->save();
            }

            PassUpdatedEvent::dispatch($pass->id, $fieldsChanged, $source);

            return $passUpdate;
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function validateFieldsAgainstTemplate(Pass $pass, array $fields): void
    {
        $template = $pass->template;
        $designData = is_array($template?->design_data) ? $template->design_data : [];
        $allowedKeys = [];

        foreach (['headerFields', 'primaryFields', 'secondaryFields', 'auxiliaryFields', 'backFields'] as $group) {
            $groupFields = $designData[$group] ?? [];

            if (! is_array($groupFields)) {
                continue;
            }

            foreach ($groupFields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? null;
                if (is_string($key) && $key !== '') {
                    $allowedKeys[$key] = true;
                }
            }
        }

        foreach (array_keys($fields) as $fieldKey) {
            if (! isset($allowedKeys[$fieldKey])) {
                throw new RuntimeException("Field [{$fieldKey}] does not exist on the pass template.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $passData
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $changeMessages
     * @return array<string, array<string, mixed>>
     */
    protected function applyFieldUpdates(array &$passData, array $fields, array $changeMessages): array
    {
        $fieldsChanged = [];

        foreach ($fields as $fieldKey => $newValue) {
            $oldValue = null;
            $updated = false;

            foreach (['headerFields', 'primaryFields', 'secondaryFields', 'auxiliaryFields', 'backFields'] as $group) {
                if (! isset($passData[$group]) || ! is_array($passData[$group])) {
                    continue;
                }

                foreach ($passData[$group] as $index => $field) {
                    if (! is_array($field) || ($field['key'] ?? null) !== $fieldKey) {
                        continue;
                    }

                    $oldValue = $field['value'] ?? null;
                    $passData[$group][$index]['value'] = $newValue;
                    if (isset($changeMessages[$fieldKey])) {
                        $passData[$group][$index]['changeMessage'] = $changeMessages[$fieldKey];
                    }
                    $updated = true;
                    break 2;
                }
            }

            if (! $updated) {
                throw new RuntimeException("Field [{$fieldKey}] does not exist on the pass template.");
            }

            $fieldsChanged[$fieldKey] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $fieldsChanged;
    }

    /**
     * @return list<string>
     */
    protected function extractPlatforms(Pass $pass): array
    {
        if (is_array($pass->platforms)) {
            return array_values(array_filter($pass->platforms, static fn ($value) => is_string($value)));
        }

        if (is_string($pass->platform) && $pass->platform !== '') {
            return [$pass->platform];
        }

        return [];
    }
}
