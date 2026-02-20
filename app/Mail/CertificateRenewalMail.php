<?php

namespace App\Mail;

use App\Models\AppleCertificate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateRenewalMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public AppleCertificate $certificate;

    public string $csr;

    public string $instructions;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, AppleCertificate $certificate, string $csr, string $instructions)
    {
        $this->user = $user;
        $this->certificate = $certificate;
        $this->csr = $csr;
        $this->instructions = $instructions;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this
            ->subject('Your Apple Wallet Certificate Renewal Request')
            ->markdown('emails.certificate-renewal')
            ->with([
                'userName' => $this->user->name,
                'fingerprint' => $this->certificate->fingerprint,
                'expiresAt' => $this->certificate->expires_at->format('M d, Y'),
                'instructions' => $this->instructions,
            ])
            ->attachData($this->csr, 'cert.certSigningRequest', [
                'mime' => 'application/pkcs10',
            ]);
    }
}
