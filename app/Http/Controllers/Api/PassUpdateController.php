<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePassFieldsRequest;
use App\Http\Resources\PassUpdateResource;
use App\Models\Pass;
use App\Services\PassUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PassUpdateController extends Controller
{
    public function __construct(private PassUpdateService $passUpdateService) {}

    public function update(UpdatePassFieldsRequest $request, Pass $pass): JsonResponse
    {
        $user = $request->user();

        if ($user === null && ! $this->hasValidHmacSignature($request)) {
            return response()->json([
                'message' => 'Invalid HMAC signature.',
            ], 401);
        }

        if ($user !== null && (int) $pass->user_id !== (int) $user->id) {
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

        try {
            $passUpdate = $this->passUpdateService->updatePassFields(
                pass: $pass,
                fields: is_array($fields) ? $fields : [],
                initiator: $user,
                source: 'api',
                changeMessages: is_array($changeMessages) ? $changeMessages : [],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => (new PassUpdateResource($passUpdate))->toArray($request),
        ]);
    }

    public function history(Request $request, Pass $pass): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $pass->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $updates = $pass->passUpdates()->latest('id')->paginate(15);

        return PassUpdateResource::collection($updates)->response();
    }

    private function hasValidHmacSignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $secret = (string) config('passkit.api.hmac_secret', config('app.key'));

        if (Str::startsWith($secret, 'base64:')) {
            $decoded = base64_decode(Str::after($secret, 'base64:'), true);
            if ($decoded !== false) {
                $secret = $decoded;
            }
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
