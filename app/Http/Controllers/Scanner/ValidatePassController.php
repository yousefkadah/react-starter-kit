<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scanner\ValidatePassRequest;
use App\Models\Pass;
use App\Models\ScanEvent;
use App\Services\PassPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidatePassController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PassPayloadService $payloadService
    ) {}

    /**
     * Validate a scanned pass payload and return its status.
     */
    public function __invoke(ValidatePassRequest $request): JsonResponse
    {
        $scannerLink = $request->attributes->get('scanner_link');
        $userId = $request->attributes->get('scanner_user_id');

        $result = $this->payloadService->validatePayload($request->validated('payload'));

        if (! $result['valid']) {
            $this->logScanEvent($userId, null, $scannerLink->id, 'invalid_signature', $request);

            return response()->json([
                'valid' => false,
                'error' => 'Invalid pass.',
            ], 400);
        }

        $pass = Pass::where('id', $result['pass_id'])
            ->where('user_id', $userId)
            ->first();

        if (! $pass) {
            $this->logScanEvent($userId, $result['pass_id'], $scannerLink->id, 'not_found', $request);

            return response()->json([
                'valid' => false,
                'error' => 'Invalid pass.',
            ], 404);
        }

        $status = $this->resolvePassStatus($pass);

        $this->logScanEvent($userId, $pass->id, $scannerLink->id, $status === 'active' ? 'success' : $status, $request);

        return response()->json([
            'valid' => $status === 'active',
            'pass' => [
                'id' => $pass->id,
                'type' => $pass->usage_type ?? 'single_use',
                'status' => $status,
                'custom_redemption_message' => $pass->custom_redemption_message,
                'redeemed_at' => $pass->redeemed_at?->toISOString(),
                'pass_type' => $pass->pass_type,
                'description' => $pass->pass_data['description'] ?? null,
            ],
        ]);
    }

    /**
     * Resolve the human-readable status of a pass.
     */
    private function resolvePassStatus(Pass $pass): string
    {
        if ($pass->isRedeemed()) {
            return 'redeemed';
        }

        if ($pass->isVoided()) {
            return 'voided';
        }

        if ($pass->isExpired()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Log a scan event in the database.
     */
    private function logScanEvent(int $userId, ?int $passId, int $scannerLinkId, string $result, Request $request): void
    {
        if (! $passId) {
            return;
        }

        ScanEvent::create([
            'user_id' => $userId,
            'pass_id' => $passId,
            'scanner_link_id' => $scannerLinkId,
            'action' => 'scan',
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
