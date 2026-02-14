<?php

namespace App\Services;

use App\Models\User;

/**
 * Service for generating Apple Certificate Signing Requests (CSR)
 * using PHP OpenSSL functions
 */
class AppleCSRService
{
    /**
     * Generate a Certificate Signing Request (CSR) for Apple Wallet
     *
     * @return string PEM-formatted CSR content
     *
     * @throws \Exception If CSR generation fails
     */
    public function generateCSR(User $user): string
    {
        // CSR subject configuration
        $subject = [
            'commonName' => $user->email,
            'organizationName' => $user->name,
            'organizationalUnitName' => 'PassKit',
            'countryName' => $user->region === 'EU' ? 'DE' : 'US',
            'stateOrProvinceName' => $user->region === 'EU' ? 'Berlin' : 'California',
            'localityName' => $user->region === 'EU' ? 'Berlin' : 'San Francisco',
        ];

        // Generate private key (2048-bit RSA)
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privateKey === false) {
            throw new \Exception('Failed to generate private key: '.openssl_error_string());
        }

        // Generate CSR
        $csr = openssl_csr_new($subject, $privateKey);

        if ($csr === false) {
            throw new \Exception('Failed to generate CSR: '.openssl_error_string());
        }

        // Export CSR to PEM format
        $csrPem = '';
        openssl_csr_export($csr, $csrPem);

        // Store private key in session for later use (temporary storage)
        session(['csr_private_key' => $privateKey]);
        session(['csr_generated_at' => now()]);

        return $csrPem;
    }

    /**
     * Download CSR as file
     *
     * @param  string  $csrContent  PEM-formatted CSR
     */
    public function downloadCSR(string $csrContent): \Illuminate\Http\Response
    {
        return response($csrContent, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="cert.certSigningRequest"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Get instructions for uploading CSR to Apple Developer Portal
     *
     * @return string Markdown-formatted instructions
     */
    public function getAppleInstructions(): string
    {
        return <<<'MARKDOWN'
# Upload CSR to Apple Developer Portal

Follow these steps to upload your Certificate Signing Request (CSR):

1. **Log into Apple Developer Portal**
   - Visit https://developer.apple.com/account
   - Sign in with your Apple ID

2. **Navigate to Certificates**
   - In the sidebar: Certificates, Identifiers & Profiles
   - Click "Certificates"

3. **Create New Certificate**
   - Click the "+" button
   - Select "Apple Wallet Pass Certificate"
   - Click "Continue"

4. **Upload CSR**
   - Upload the `cert.certSigningRequest` file you just downloaded
   - Click "Continue"

5. **Download Certificate**
   - Download the generated `.cer` file
   - Keep this file safe - you'll upload it to PassKit next

6. **Back to PassKit**
   - Return here and upload the `.cer` file
MARKDOWN;
    }
}
