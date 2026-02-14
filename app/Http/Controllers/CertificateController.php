<?php

namespace App\Http\Controllers;

use App\Jobs\MarkOnboardingStepsForWalletJob;
use App\Jobs\TierProgressionJob;
use App\Mail\CertificateRenewalMail;
use App\Models\AppleCertificate;
use App\Models\GoogleCredential;
use App\Services\AppleCSRService;
use App\Services\CertificateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

/**
 * Controller for managing Apple and Google Wallet certificates/credentials
 */
class CertificateController extends Controller
{
    protected AppleCSRService $csrService;

    protected CertificateValidationService $validationService;

    public function __construct(
        AppleCSRService $csrService,
        CertificateValidationService $validationService
    ) {
        $this->csrService = $csrService;
        $this->validationService = $validationService;
    }

    /**
     * Download Apple CSR (Certificate Signing Request)
     *
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function downloadAppleCSR()
    {
        $user = Auth::user();

        try {
            $csr = $this->csrService->generateCSR($user);

            // Send email with CSR and instructions
            \Illuminate\Support\Facades\Mail::raw(
                $this->csrService->getAppleInstructions(),
                function ($message) use ($user) {
                    $message
                        ->to($user->email)
                        ->subject('Your Apple Wallet Certificate Signing Request');
                }
            );

            return $this->csrService->downloadCSR($csr);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate CSR: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload Apple Wallet certificate (.cer file)
     *
     * @return JsonResponse
     */
    public function uploadAppleCertificate(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'certificate' => 'required|file|mimes:cer,pem|max:512',
            'password' => 'nullable|string|max:255',
        ]);

        $file = $request->file('certificate');

        // Validate certificate
        $validation = $this->validationService->validateAppleCertificate($file);

        if (! $validation['valid']) {
            return response()->json([
                'message' => 'Certificate validation failed',
                'errors' => $validation['errors'],
            ], 422);
        }

        try {
            // Store certificate file (encrypted password if provided)
            $certContent = $file->get();
            $encryptedPassword = $request->password ? Crypt::encryptString($request->password) : null;

            // Create AppleCertificate record
            $certificate = AppleCertificate::create([
                'user_id' => $user->id,
                'path' => 'certificates/apple/'.uniqid().'.cer',
                'password' => $encryptedPassword,
                'valid_from' => $validation['valid_from'],
                'expiry_date' => $validation['expiry_date'],
                'fingerprint' => $validation['fingerprint'],
            ]);

            // Trigger tier progression evaluation
            TierProgressionJob::dispatch($user);
            MarkOnboardingStepsForWalletJob::dispatch($user->id);

            return response()->json([
                'message' => 'Certificate uploaded successfully',
                'certificate' => [
                    'id' => $certificate->id,
                    'fingerprint' => $validation['fingerprint'],
                    'valid_from' => $validation['valid_from'],
                    'expiry_date' => $validation['expiry_date'],
                    'days_until_expiry' => now()->diffInDays($certificate->expiry_date),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to store certificate: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload Google Wallet service account JSON
     *
     * @return JsonResponse
     */
    public function uploadGoogleCredential(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'credentials' => 'required|file|mimes:json|max:50',
        ]);

        $file = $request->file('credentials');

        // Validate Google credentials
        $validation = $this->validationService->validateGoogleJSON($file);

        if (! $validation['valid']) {
            return response()->json([
                'message' => 'Credentials validation failed',
                'errors' => $validation['errors'],
            ], 422);
        }

        try {
            // Store credential JSON (encrypted private_key)
            $credContent = json_decode($file->get(), true);
            $encryptedPrivateKey = Crypt::encryptString($credContent['private_key']);

            // Create GoogleCredential record
            $credential = GoogleCredential::create([
                'user_id' => $user->id,
                'issuer_id' => $validation['issuer_id'],
                'private_key' => $encryptedPrivateKey,
                'project_id' => $validation['project_id'],
                'last_rotated_at' => now(),
            ]);

            // Trigger tier progression evaluation
            TierProgressionJob::dispatch($user);
            MarkOnboardingStepsForWalletJob::dispatch($user->id);

            return response()->json([
                'message' => 'Google credentials uploaded successfully',
                'credential' => [
                    'id' => $credential->id,
                    'issuer_id' => $validation['issuer_id'],
                    'project_id' => $validation['project_id'],
                    'last_rotated_at' => $credential->last_rotated_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to store credentials: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Apple certificate
     *
     * @return JsonResponse
     */
    public function deleteAppleCertificate(AppleCertificate $certificate)
    {
        $user = Auth::user();

        // Authorization check
        if ($certificate->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $certificate->delete();

        return response()->json([
            'message' => 'Certificate deleted successfully',
        ]);
    }

    /**
     * Delete Google credential
     *
     * @return JsonResponse
     */
    public function deleteGoogleCredential(GoogleCredential $credential)
    {
        $user = Auth::user();

        // Authorization check
        if ($credential->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $credential->delete();

        return response()->json([
            'message' => 'Credentials deleted successfully',
        ]);
    }

    /**
     * Renew Apple certificate (generate new CSR)
     *
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function renewAppleCertificate(AppleCertificate $certificate)
    {
        $user = Auth::user();

        // Authorization check
        if ($certificate->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            // Generate new CSR
            $csr = $this->csrService->generateCSR($user);

            // Send email with renewal instructions
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new CertificateRenewalMail(
                    $user,
                    $certificate,
                    $csr,
                    $this->csrService->getAppleInstructions()
                ));

            return $this->csrService->downloadCSR($csr);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate renewal CSR: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rotate Google credentials (generate new key)
     * Note: This requires manual rotation on Google Cloud console
     *
     * @return JsonResponse
     */
    public function rotateGoogleCredential(GoogleCredential $credential)
    {
        $user = Auth::user();

        // Authorization check
        if ($credential->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Send email with rotation instructions
        \Illuminate\Support\Facades\Mail::raw(
            "Your Google Wallet credentials should be rotated periodically for security.\n\n".
            "To rotate your credentials:\n\n".
            "1. Log into Google Cloud Console\n".
            "2. Navigate to Service Accounts\n".
            "3. Select the 'passkit' service account\n".
            "4. Go to the 'Keys' tab\n".
            "5. Create a new JSON key\n".
            "6. Download and upload it here\n",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Google Wallet Credentials Rotation Instructions');
            }
        );

        return response()->json([
            'message' => 'Rotation instructions sent to your email',
            'next_step' => 'Upload your new credentials JSON key',
        ]);
    }
}
