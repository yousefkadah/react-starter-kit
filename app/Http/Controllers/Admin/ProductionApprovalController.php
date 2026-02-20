<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TierProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionApprovalController extends Controller
{
    protected TierProgressionService $tierService;

    public function __construct(TierProgressionService $tierService)
    {
        $this->tierService = $tierService;
    }

    /**
     * List pending production tier requests.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = User::whereNotNull('production_requested_at')
            ->whereNull('production_approved_at')
            ->whereNull('production_rejected_at')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'industry' => $user->industry,
                'requested_at' => $user->production_requested_at,
                'current_tier' => $user->tier,
            ]),
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    /**
     * View approved production requests.
     */
    public function approved(): JsonResponse
    {
        $requests = User::whereNotNull('production_approved_at')
            ->with('productionApprovedBy')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'approved_at' => $user->production_approved_at,
                'approved_by' => $user->productionApprovedBy?->name,
                'current_tier' => $user->tier,
            ]),
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    /**
     * View rejected production requests.
     */
    public function rejected(): JsonResponse
    {
        $requests = User::whereNotNull('production_rejected_at')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rejected_at' => $user->production_rejected_at,
                'rejection_reason' => $user->production_rejected_reason,
                'current_tier' => $user->tier,
            ]),
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    /**
     * Request production tier upgrade.
     */
    public function requestProduction(): JsonResponse
    {
        $user = Auth::user();

        try {
            $this->tierService->submitProductionRequest($user);

            return response()->json([
                'message' => 'Production tier request submitted successfully',
                'next_step' => 'Admin will review your request within 24 hours',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Cannot request production: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Request to go live (pre-launch checklist validation).
     */
    public function requestLive(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->tier !== 'Production') {
            return response()->json([
                'message' => 'User must be in Production tier to request live',
            ], 422);
        }

        $request->validate([
            'tested_on_device' => 'boolean',
        ]);

        try {
            if ($request->boolean('tested_on_device')) {
                $this->tierService->markChecklistItem($user, 'tested_on_device', true);
            }

            $valid = $this->tierService->requestLive($user);

            if (! $valid) {
                return response()->json([
                    'message' => 'Pre-launch checklist requirements not met',
                    'missing_requirements' => $this->getMissingRequirements($user),
                ], 422);
            }

            return response()->json([
                'message' => 'Pre-launch checklist validated',
                'ready_for_live' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Validation failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Advance user to live tier.
     */
    public function goLive(): JsonResponse
    {
        $user = Auth::user();

        if ($user->tier !== 'Production') {
            return response()->json([
                'message' => 'User must be in Production tier to go live',
            ], 422);
        }

        try {
            $this->tierService->advanceToLive($user);

            return response()->json([
                'message' => 'Congratulations! Your account is now LIVE 🎉',
                'user' => [
                    'id' => $user->id,
                    'tier' => $user->tier,
                    'live_approved_at' => $user->live_approved_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to advance to live: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get missing pre-launch checklist requirements.
     */
    private function getMissingRequirements(User $user): array
    {
        $requirements = [];

        $hasAppleCert = $user->appleCertificates()->whereNull('deleted_at')->where('expiry_date', '>', now())->exists();
        if (! $hasAppleCert) {
            $requirements[] = 'Valid Apple Wallet certificate required';
        }

        $hasGoogleCred = $user->googleCredentials()->whereNull('deleted_at')->exists();
        if (! $hasGoogleCred) {
            $requirements[] = 'Google Wallet credentials required';
        }

        $hasPasses = method_exists($user, 'passes') && $user->passes()->exists();
        if (! $hasPasses) {
            $requirements[] = 'At least one pass must be created';
        }

        $checklist = $user->pre_launch_checklist ?? [];
        if (! isset($checklist['tested_on_device']) || ! $checklist['tested_on_device']) {
            $requirements[] = 'Must confirm testing on device';
        }

        if (empty($user->name) || empty($user->business_name ?? null)) {
            $requirements[] = 'Complete user profile information';
        }

        return $requirements;
    }

    /**
     * Approve a production tier request.
     */
    public function approve(User $user): JsonResponse
    {
        $admin = Auth::user();

        try {
            $this->tierService->approveProduction($user, $admin);

            return response()->json([
                'message' => 'User approved for production tier',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tier' => 'Production',
                    'approved_at' => $user->production_approved_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve user: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a production tier request.
     */
    public function reject(Request $request, User $user): JsonResponse
    {
        $admin = Auth::user();

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->tierService->rejectProduction($user, $admin, $request->input('reason'));

            return response()->json([
                'message' => 'Production tier request rejected',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tier' => $user->tier,
                    'rejection_reason' => $request->input('reason'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject request: '.$e->getMessage(),
            ], 422);
        }
    }
}
