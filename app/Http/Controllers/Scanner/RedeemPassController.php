<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scanner\RedeemPassRequest;
use App\Models\Pass;
use App\Models\ScanEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedeemPassController extends Controller
{
    /**
     * Redeem or log a visit for a pass.
     *
     * Single-use passes are redeemed (status set to "redeemed") with pessimistic locking.
     * Multi-use passes log a visit without changing status.
     */
    public function __invoke(RedeemPassRequest $request): JsonResponse
    {
        $scannerLink = $request->attributes->get('scanner_link');
        $userId = $request->attributes->get('scanner_user_id');
        $passId = $request->validated('pass_id');

        $pass = Pass::where('id', $passId)
            ->where('user_id', $userId)
            ->first();

        if (! $pass) {
            return response()->json([
                'success' => false,
                'error' => 'Pass not found.',
            ], 404);
        }

        // Check voided status
        if ($pass->isVoided()) {
            $this->logScanEvent($userId, $pass->id, $scannerLink->id, 'redeem', 'voided', $request);

            return response()->json([
                'success' => false,
                'error' => 'This pass is voided and cannot be redeemed.',
            ], 422);
        }

        // Check expired status
        if ($pass->isExpired()) {
            $this->logScanEvent($userId, $pass->id, $scannerLink->id, 'redeem', 'expired', $request);

            return response()->json([
                'success' => false,
                'error' => 'This pass has expired and cannot be redeemed.',
            ], 422);
        }

        // Multi-use passes: log a visit without voiding
        if ($pass->isMultiUse()) {
            return $this->handleMultiUseVisit($pass, $userId, $scannerLink->id, $request);
        }

        // Single-use passes: redeem with pessimistic locking
        return $this->handleSingleUseRedemption($pass, $userId, $scannerLink->id, $request);
    }

    /**
     * Handle single-use pass redemption with pessimistic locking.
     */
    private function handleSingleUseRedemption(Pass $pass, int $userId, int $scannerLinkId, Request $request): JsonResponse
    {
        return DB::transaction(function () use ($pass, $userId, $scannerLinkId, $request): JsonResponse {
            // Re-fetch with pessimistic lock to prevent double-redemption
            $lockedPass = Pass::where('id', $pass->id)->lockForUpdate()->first();

            if ($lockedPass->isRedeemed()) {
                $this->logScanEvent($userId, $lockedPass->id, $scannerLinkId, 'redeem', 'already_redeemed', $request);

                return response()->json([
                    'success' => false,
                    'error' => 'This pass has already been redeemed.',
                ], 409);
            }

            $lockedPass->markAsRedeemed();

            $this->logScanEvent($userId, $lockedPass->id, $scannerLinkId, 'redeem', 'success', $request);

            return response()->json([
                'success' => true,
                'message' => 'Pass redeemed successfully.',
                'pass' => [
                    'id' => $lockedPass->id,
                    'status' => 'redeemed',
                    'redeemed_at' => $lockedPass->fresh()->redeemed_at?->toISOString(),
                    'custom_redemption_message' => $lockedPass->custom_redemption_message,
                ],
            ]);
        });
    }

    /**
     * Handle multi-use pass visit logging.
     */
    private function handleMultiUseVisit(Pass $pass, int $userId, int $scannerLinkId, Request $request): JsonResponse
    {
        $this->logScanEvent($userId, $pass->id, $scannerLinkId, 'visit', 'success', $request);

        return response()->json([
            'success' => true,
            'message' => 'Visit logged successfully.',
            'pass' => [
                'id' => $pass->id,
                'status' => $pass->status,
                'custom_redemption_message' => $pass->custom_redemption_message,
            ],
        ]);
    }

    /**
     * Log a scan event in the database.
     */
    private function logScanEvent(int $userId, int $passId, int $scannerLinkId, string $action, string $result, Request $request): void
    {
        ScanEvent::create([
            'user_id' => $userId,
            'pass_id' => $passId,
            'scanner_link_id' => $scannerLinkId,
            'action' => $action,
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
