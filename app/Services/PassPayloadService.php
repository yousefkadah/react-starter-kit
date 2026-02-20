<?php

namespace App\Services;

use App\Models\Pass;

class PassPayloadService
{
    /**
     * Generate an HMAC-signed payload for a pass.
     *
     * The payload format is: base64(passId.hmac_signature)
     * This keeps QR codes small while preventing forgery.
     */
    public function generatePayload(Pass $pass): string
    {
        $signature = hash_hmac('sha256', (string) $pass->id, config('app.key'));

        return base64_encode($pass->id.'.'.$signature);
    }

    /**
     * Validate and decode an HMAC-signed payload.
     *
     * @return array{valid: bool, pass_id: int|null}
     */
    public function validatePayload(string $payload): array
    {
        $decoded = base64_decode($payload, strict: true);

        if ($decoded === false) {
            return ['valid' => false, 'pass_id' => null];
        }

        $parts = explode('.', $decoded, 2);

        if (count($parts) !== 2) {
            return ['valid' => false, 'pass_id' => null];
        }

        [$passId, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $passId, config('app.key'));

        if (! hash_equals($expectedSignature, $signature)) {
            return ['valid' => false, 'pass_id' => null];
        }

        return ['valid' => true, 'pass_id' => (int) $passId];
    }
}
