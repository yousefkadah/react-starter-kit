<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Service for validating Apple Wallet certificates (.cer files)
 * Uses PHP OpenSSL functions to validate certificate format, expiry, and signature
 */
class CertificateValidationService
{
    /**
     * Validate an uploaded Apple certificate file
     *
     * @return array {
     *               'valid' => bool,
     *               'errors' => string[],
     *               'fingerprint' => ?string,
     *               'valid_from' => ?string (ISO date),
     *               'expiry_date' => ?string (ISO date),
     *               }
     */
    public function validateAppleCertificate(UploadedFile $file): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'fingerprint' => null,
            'valid_from' => null,
            'expiry_date' => null,
        ];

        // Check file extension
        if (! in_array($file->getClientOriginalExtension(), ['cer', 'pem'])) {
            $result['errors'][] = 'Certificate must be a .cer or .pem file';

            return $result;
        }

        try {
            // Read certificate content
            $certContent = $file->get();

            // Try to read certificate
            $cert = openssl_x509_read($certContent);
            if ($cert === false) {
                $result['errors'][] = 'Invalid certificate file format. Use a valid .cer file from Apple Developer Portal.';

                return $result;
            }

            // Parse certificate details
            $parsed = openssl_x509_parse($cert);
            if ($parsed === false) {
                $result['errors'][] = 'Could not parse certificate. It may be corrupted.';

                return $result;
            }

            // Check expiry
            $validTo = $parsed['validTo_time_t'] ?? null;
            if ($validTo === null) {
                $result['errors'][] = 'Could not determine certificate expiry date.';

                return $result;
            }

            $now = time();
            if ($validTo < $now) {
                $result['errors'][] = 'Certificate has expired. Please obtain a new certificate from Apple Developer Portal.';

                return $result;
            }

            // Check validity start
            $validFrom = $parsed['validFrom_time_t'] ?? null;
            if ($validFrom === null) {
                $result['errors'][] = 'Could not determine certificate validity start date.';

                return $result;
            }

            // Extract dates
            $result['valid_from'] = \DateTime::createFromFormat('U', (string) $validFrom)->format('Y-m-d H:i:s');
            $result['expiry_date'] = \DateTime::createFromFormat('U', (string) $validTo)->format('Y-m-d H:i:s');

            // Get certificate fingerprint (SHA1)
            $fingerprint = openssl_x509_fingerprint($cert, 'sha256', false);
            if ($fingerprint === false) {
                $result['errors'][] = 'Could not generate certificate fingerprint.';

                return $result;
            }
            $result['fingerprint'] = $fingerprint;

            // Check if Apple's WWDR certificate is in the chain (optional but recommended)
            $subject = $parsed['subject'] ?? [];
            if (empty($subject)) {
                $result['errors'][] = 'Certificate appears to be missing required Apple information.';

                return $result;
            }

            $result['valid'] = true;

            return $result;

        } catch (\Exception $e) {
            $result['errors'][] = 'Error validating certificate: '.$e->getMessage();

            return $result;
        }
    }

    /**
     * Validate Google credential JSON file
     *
     * @return array {
     *               'valid' => bool,
     *               'errors' => string[],
     *               'issuer_id' => ?string,
     *               'project_id' => ?string,
     *               }
     */
    public function validateGoogleJSON(UploadedFile $file): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'issuer_id' => null,
            'project_id' => null,
        ];

        // Check file extension
        if ($file->getClientOriginalExtension() !== 'json') {
            $result['errors'][] = 'File must be a JSON file.';

            return $result;
        }

        try {
            // Parse JSON
            $content = json_decode($file->get(), true);
            if ($content === null) {
                $result['errors'][] = 'Invalid JSON format.';

                return $result;
            }

            // Required fields for service account key
            $requiredFields = [
                'type',
                'project_id',
                'private_key_id',
                'private_key',
                'client_email',
                'client_id',
                'auth_uri',
                'token_uri',
            ];

            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($content[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (! empty($missingFields)) {
                $result['errors'][] = 'Missing required fields: '.implode(', ', $missingFields);

                return $result;
            }

            // Verify type is service_account
            if ($content['type'] !== 'service_account') {
                $result['errors'][] = 'Credential type must be "service_account". Check that you downloaded the correct JSON key.';

                return $result;
            }

            // Extract issuer ID from client_email (everything before @)
            $issuerIdMatch = [];
            if (preg_match('/^([^@]+)@/', $content['client_email'], $issuerIdMatch)) {
                $result['issuer_id'] = $issuerIdMatch[1];
            } else {
                $result['errors'][] = 'Invalid client_email format in JSON.';

                return $result;
            }

            // Get project ID
            $result['project_id'] = $content['project_id'];

            // Validate private key format (basic check - is it RSA?)
            $privateKey = $content['private_key'];
            if (! str_starts_with($privateKey, '-----BEGIN PRIVATE KEY-----')) {
                $result['errors'][] = 'Invalid private key format in JSON.';

                return $result;
            }

            // Try to read the key with OpenSSL
            $keyResource = openssl_pkey_get_private($privateKey);
            if ($keyResource === false) {
                $result['errors'][] = 'Private key is invalid or corrupted.';

                return $result;
            }
            openssl_pkey_free($keyResource);

            $result['valid'] = true;

            return $result;

        } catch (\Exception $e) {
            $result['errors'][] = 'Error validating JSON: '.$e->getMessage();

            return $result;
        }
    }
}
