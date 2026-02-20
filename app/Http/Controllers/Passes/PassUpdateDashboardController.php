<?php

namespace App\Http\Controllers\Passes;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdatePassesRequest;
use App\Http\Requests\UpdatePassFieldsRequest;
use App\Jobs\BulkPassUpdateJob;
use App\Jobs\ProcessPassUpdateJob;
use App\Models\BulkUpdate;
use App\Models\Pass;
use App\Models\PassTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassUpdateDashboardController extends Controller
{
    public function update(UpdatePassFieldsRequest $request, Pass $pass): JsonResponse
    {
        if ((int) $pass->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($pass->isVoided()) {
            return response()->json([
                'message' => 'Voided passes cannot be updated.',
            ], 409);
        }

        $fields = $request->input('fields', []);
        $changeMessages = $request->input('change_messages', []);

        $payloadSize = strlen((string) json_encode($fields));
        if ($payloadSize > 10240) {
            return response()->json([
                'message' => 'Pass data exceeds 10KB limit after update.',
            ], 422);
        }

        ProcessPassUpdateJob::dispatch(
            passId: $pass->id,
            fields: $fields,
            initiatorId: $request->user()->id,
            source: 'dashboard',
            changeMessages: is_array($changeMessages) ? $changeMessages : [],
        );

        return response()->json([
            'message' => 'Pass update queued.',
            'pass_id' => $pass->id,
            'has_registered_devices' => $pass->hasRegisteredDevices(),
            'warning' => $pass->hasRegisteredDevices() ? null : 'No registered Apple Wallet devices for this pass.',
        ]);
    }

    public function history(Request $request, Pass $pass): JsonResponse
    {
        if ((int) $pass->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $updates = $pass->passUpdates()
            ->latest('id')
            ->paginate(15);

        return response()->json($updates);
    }

    public function bulkUpdate(BulkUpdatePassesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $template = PassTemplate::query()->findOrFail((int) $validated['pass_template_id']);

        if ($template->hasBulkUpdateInProgress()) {
            return response()->json([
                'message' => 'A bulk update is already in progress for this template.',
            ], 409);
        }

        $passesQuery = Pass::query()
            ->where('pass_template_id', $template->id)
            ->where('user_id', $request->user()->id);

        $filters = $validated['filters'] ?? [];
        if (($filters['status'] ?? null) === 'active') {
            $passesQuery->where('status', 'active');
        }

        if (($filters['platform'] ?? null) === 'apple') {
            $passesQuery->whereJsonContains('platforms', 'apple');
        }

        if (($filters['platform'] ?? null) === 'google') {
            $passesQuery->whereJsonContains('platforms', 'google');
        }

        $totalCount = $passesQuery->count();

        $bulkUpdate = BulkUpdate::query()->create([
            'user_id' => $request->user()->id,
            'pass_template_id' => $template->id,
            'field_key' => $validated['field_key'],
            'field_value' => (string) $validated['field_value'],
            'filters' => is_array($filters) ? $filters : null,
            'status' => 'pending',
            'total_count' => $totalCount,
            'processed_count' => 0,
            'failed_count' => 0,
        ]);

        BulkPassUpdateJob::dispatch($bulkUpdate->id);

        return response()->json([
            'data' => [
                'id' => $bulkUpdate->id,
                'status' => $bulkUpdate->status,
                'total_count' => $bulkUpdate->total_count,
                'message' => "Bulk update queued for {$bulkUpdate->total_count} passes",
            ],
        ], 202);
    }

    public function bulkUpdateStatus(Request $request, BulkUpdate $bulkUpdate): JsonResponse
    {
        if ((int) $bulkUpdate->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return response()->json([
            'data' => [
                'id' => $bulkUpdate->id,
                'status' => $bulkUpdate->status,
                'total_count' => $bulkUpdate->total_count,
                'processed_count' => $bulkUpdate->processed_count,
                'failed_count' => $bulkUpdate->failed_count,
                'started_at' => $bulkUpdate->started_at,
                'completed_at' => $bulkUpdate->completed_at,
            ],
        ]);
    }
}
