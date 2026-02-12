<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminProductionRequestMail;
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
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * List pending production tier requests.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $admin = Auth::user();
        $this->authorize('viewQueue', [User::class, $admin]);

        $requests = User::whereNotNull('production_requested_at')
            ->whereNull('production_approved_at')
            ->whereNull('production_rejected_at')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn($user) => [
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
     * 
     * @return JsonResponse
     */
    public function approved(): JsonResponse
    {
        $admin = Auth::user();
        $this->authorize('viewQueue', [User::class, $admin]);

        $requests = User::whereNotNull('production_approved_at')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'approved_at' => $user->production_approved_at,
                'approved_by' => $user->approvedBy?->name,
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
     * 
     * @return JsonResponse
     */
    public function rejected(): JsonResponse
    {
        $admin = Auth::user();
        $this->authorize('viewQueue', [User::class, $admin]);

        $requests = User::whereNotNull('production_rejected_at')
            ->paginate(15);

        return response()->json([
            'requests' => $requests->map(fn($user) => [
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
     * Approve a production tier request.
     * 
     * @param User $user
     * @return JsonResponse
     */
    public function approve(User $user): JsonResponse
    {
        $admin = Auth::user();
        $this->authorize('approve', [User::class, $admin, $user]);

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
                'message' => 'Failed to approve user: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a production tier request.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function reject(Request $request, User $user): JsonResponse
    {
        $admin = Auth::user();
        $this->authorize('reject', [User::class, $admin, $user]);

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
                'message' => 'Failed to reject request: ' . $e->getMessage(),
            ], 422);
        }
    }
}
