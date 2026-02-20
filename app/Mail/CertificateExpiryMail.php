<?php

namespace App\Mail;

use App\Models\AppleCertificate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public AppleCertificate $certificate,
        public int $daysRemaining
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->daysRemaining) {
            30 => 'Your Apple Wallet certificate expires in 30 days',
            7 => 'Your Apple Wallet certificate expires in 7 days',
            0 => 'Your Apple Wallet certificate has expired',
            default => 'Apple Wallet certificate expiry notice',
        };

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.certificate-expiry',
            with: [
                'user' => $this->user,
                'certificate' => $this->certificate,
                'daysRemaining' => $this->daysRemaining,
                'manageUrl' => config('app.url').'/settings/certificates/apple',
            ],
        );
    }
}
