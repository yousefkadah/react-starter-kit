<?php

namespace App\Services;

use App\Models\AppleCertificate;
use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ApplePushService
{
    protected string $certificatePath;

    protected string $certificatePassword;

    protected string $environment;

    public function __construct(string $certificatePath, string $certificatePassword = '', string $environment = 'production')
    {
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $certificatePassword;
        $this->environment = $environment;
    }

    public static function forUser(User $user): self
    {
        /** @var AppleCertificate|null $certificate */
        $certificate = $user->appleCertificates()
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', '!=', 'archived');
            })
            ->latest('id')
            ->first();

        if ($certificate === null) {
            throw new RuntimeException('No active Apple certificate found for user.');
        }

        $certificatesDisk = (string) config('passkit.storage.certificates_disk', 'local');
        $certificatePath = $certificate->path;

        if (! file_exists($certificatePath)) {
            $certificatePath = Storage::disk($certificatesDisk)->path($certificate->path);
        }

        $certificatePassword = '';
        if (is_string($certificate->password) && $certificate->password !== '') {
            try {
                $certificatePassword = Crypt::decryptString($certificate->password);
            } catch (\Throwable) {
                $certificatePassword = $certificate->password;
            }
        }

        return new self(
            certificatePath: $certificatePath,
            certificatePassword: $certificatePassword,
            environment: (string) config('passkit.push.apns_environment', 'production'),
        );
    }

    /**
     * Send a wallet push notification to Apple APNS.
     */
    public function sendPush(string $deviceToken, string $passTypeIdentifier): bool
    {
        $this->guardRateLimit($passTypeIdentifier);

        $baseUrl = $this->environment === 'sandbox'
            ? 'https://api.development.push.apple.com:443'
            : 'https://api.push.apple.com:443';

        /** @var HttpResponse $response */
        $response = $this->httpClient($passTypeIdentifier)
            ->withBody('{}', 'application/json')
            ->post("{$baseUrl}/3/device/{$deviceToken}");

        if ($response->successful()) {
            return true;
        }

        if ($response->status() === 410) {
            DeviceRegistration::query()
                ->where('push_token', $deviceToken)
                ->update(['is_active' => false]);

            return false;
        }

        if ($response->status() === 429) {
            throw new RuntimeException('APNS rate limit reached. Retry required.');
        }

        throw new RuntimeException('Failed to send APNS push: '.$response->body());
    }

    protected function guardRateLimit(string $passTypeIdentifier): void
    {
        $limit = (int) config('passkit.push.rate_limit_per_second', 50);
        $key = sprintf('apns-rate-limit:%s:%s', $passTypeIdentifier, now()->timestamp);
        $counter = Cache::increment($key);

        if ($counter === 1) {
            Cache::put($key, 1, now()->addSecond());
        }

        if ($counter > $limit) {
            throw new RuntimeException('APNS rate limit reached. Retry required.');
        }
    }

    protected function httpClient(string $passTypeIdentifier): PendingRequest
    {
        $curlOptions = [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        ];

        if (is_string($this->certificatePath) && $this->certificatePath !== '' && file_exists($this->certificatePath)) {
            $curlOptions[CURLOPT_SSLCERT] = $this->certificatePath;
            $curlOptions[CURLOPT_SSLCERTPASSWD] = $this->certificatePassword;
        }

        return Http::withOptions([
            'curl' => $curlOptions,
        ])->withHeaders([
            'apns-topic' => $passTypeIdentifier,
            'apns-push-type' => 'alert',
            'apns-priority' => '5',
        ]);
    }
}
