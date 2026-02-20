<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserApprovedMail;
use App\Mail\UserRejectedMail;
use App\Models\User;
use App\Services\EmailDomainService;
use Illuminate\Support\Facades\Mail;

class AdminApprovalController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private EmailDomainService $emailDomainService) {}

    /**
     * Get pending approval queue.
     */
    public function index()
    {
        $pending = User::where('approval_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'pending_count' => $pending->total(),
            'users' => $pending->items(),
            'pagination' => [
                'current_page' => $pending->currentPage(),
                'total_pages' => $pending->lastPage(),
                'per_page' => $pending->perPage(),
            ],
        ]);
    }

    /**
     * Approve a pending user.
     */
    public function approve(User $user)
    {
        // Validate user is actually pending
        if ($user->approval_status !== 'pending') {
            return response()->json([
                'message' => 'User is not pending approval.',
            ], 400);
        }

        // Approve the user
        $admin = auth()->user();
        $this->emailDomainService->approveAccount($user, $admin);

        // Send approval email
        Mail::to($user->email)->send(new UserApprovedMail($user));

        return response()->json([
            'message' => 'User has been approved.',
            'user' => $user,
        ]);
    }

    /**
     * Reject a pending user.
     */
    public function reject(User $user)
    {
        $reason = request()->input('reason');

        // Validate user is actually pending
        if ($user->approval_status !== 'pending') {
            return response()->json([
                'message' => 'User is not pending approval.',
            ], 400);
        }

        // Reject the user
        $user->update([
            'approval_status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => auth()->user()->id,
        ]);

        // Send rejection email
        Mail::to($user->email)->send(new UserRejectedMail($user, $reason));

        return response()->json([
            'message' => 'User has been rejected.',
            'user' => $user,
        ]);
    }

    /**
     * Get approved accounts.
     */
    public function approved()
    {
        $approved = User::where('approval_status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->paginate(50);

        return response()->json([
            'total' => $approved->total(),
            'users' => $approved->items(),
            'pagination' => [
                'current_page' => $approved->currentPage(),
                'total_pages' => $approved->lastPage(),
                'per_page' => $approved->perPage(),
            ],
        ]);
    }

    /**
     * Get rejected accounts.
     */
    public function rejected()
    {
        $rejected = User::where('approval_status', 'rejected')
            ->orderBy('approved_at', 'desc')
            ->paginate(50);

        return response()->json([
            'total' => $rejected->total(),
            'users' => $rejected->items(),
            'pagination' => [
                'current_page' => $rejected->currentPage(),
                'total_pages' => $rejected->lastPage(),
                'per_page' => $rejected->perPage(),
            ],
        ]);
    }
}
