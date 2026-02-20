<?php

namespace App\Http\Controllers\Passes;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Models\Pass;
use App\Services\ApplePassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AppleWebServiceController extends Controller
{
    public function registerDevice(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->resolveAuthorizedPass($request, $passTypeIdentifier, $serialNumber);
        if ($pass === null) {
            return response('', 401);
        }

        $validated = $request->validate([
            'pushToken' => ['required', 'string'],
        ]);

        $registration = DeviceRegistration::query()->updateOrCreate(
            [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ],
            [
                'push_token' => (string) $validated['pushToken'],
                'user_id' => (int) $pass->user_id,
                'is_active' => true,
            ]
        );

        return response('', $registration->wasRecentlyCreated ? 201 : 200);
    }

    public function unregisterDevice(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->resolveAuthorizedPass($request, $passTypeIdentifier, $serialNumber);
        if ($pass === null) {
            return response('', 401);
        }

        DeviceRegistration::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->delete();

        return response('', 200);
    }

    public function getUpdatedPasses(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier): JsonResponse|Response
    {
        $registrations = DeviceRegistration::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->active()
            ->get(['serial_number']);

        if ($registrations->isEmpty()) {
            return response('', 204);
        }

        $serialNumbers = $registrations->pluck('serial_number')->all();

        $query = Pass::query()
            ->whereIn('serial_number', $serialNumbers)
            ->whereHas('user', function ($userQuery) use ($passTypeIdentifier): void {
                $userQuery->where('apple_pass_type_id', $passTypeIdentifier);
            });

        $updatedSinceRaw = $request->query('passesUpdatedSince');
        if (is_string($updatedSinceRaw) && ctype_digit($updatedSinceRaw)) {
            $query->where('updated_at', '>', Carbon::createFromTimestampUTC((int) $updatedSinceRaw));
        }

        $passes = $query
            ->orderBy('updated_at')
            ->get(['serial_number', 'updated_at']);

        if ($passes->isEmpty()) {
            return response('', 204);
        }

        $lastUpdatedTimestamp = (string) $passes->max(function (Pass $pass): int {
            return (int) $pass->updated_at?->timestamp;
        });

        return response()->json([
            'serialNumbers' => $passes->pluck('serial_number')->values()->all(),
            'lastUpdated' => $lastUpdatedTimestamp,
        ]);
    }

    public function getLatestPass(Request $request, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->resolveAuthorizedPass($request, $passTypeIdentifier, $serialNumber);
        if ($pass === null) {
            return response('', 401);
        }

        $ifModifiedSince = $request->header('If-Modified-Since');
        if (is_string($ifModifiedSince) && $ifModifiedSince !== '') {
            try {
                $ifModifiedSinceDate = Carbon::parse($ifModifiedSince);
                if ($pass->updated_at !== null && $pass->updated_at->lte($ifModifiedSinceDate)) {
                    return response('', 304, [
                        'Last-Modified' => $pass->updated_at->toRfc7231String(),
                    ]);
                }
            } catch (\Throwable) {
            }
        }

        $diskName = (string) config('passkit.storage.passes_disk', 'local');
        $disk = Storage::disk($diskName);

        $pkpassPath = is_string($pass->pkpass_path) ? $pass->pkpass_path : '';
        if ($pkpassPath === '' || ! $disk->exists($pkpassPath)) {
            $pkpassPath = ApplePassService::forUser($pass->user)->generate($pass);
        }

        $contents = $disk->get($pkpassPath);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Last-Modified' => ($pass->updated_at ?? now())->toRfc7231String(),
        ]);
    }

    public function logErrors(Request $request): Response
    {
        $validated = $request->validate([
            'logs' => ['required', 'array'],
            'logs.*' => ['string'],
        ]);

        foreach ($validated['logs'] as $logMessage) {
            Log::warning('Apple Wallet pass log', [
                'message' => (string) $logMessage,
            ]);
        }

        return response('', 200);
    }

    private function resolveAuthorizedPass(Request $request, string $passTypeIdentifier, string $serialNumber): ?Pass
    {
        $pass = Pass::query()
            ->where('serial_number', $serialNumber)
            ->whereHas('user', function ($userQuery) use ($passTypeIdentifier): void {
                $userQuery->where('apple_pass_type_id', $passTypeIdentifier);
            })
            ->first();

        if ($pass === null) {
            return null;
        }

        $providedToken = $this->extractApplePassToken($request);
        $expectedToken = is_string($pass->authentication_token) ? $pass->authentication_token : '';

        if ($providedToken === null || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return null;
        }

        return $pass;
    }

    private function extractApplePassToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! str_starts_with($header, 'ApplePass ')) {
            return null;
        }

        $token = trim(substr($header, strlen('ApplePass ')));

        return $token === '' ? null : $token;
    }
}
