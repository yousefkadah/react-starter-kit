<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TierAdvancedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $newTier
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your PassKit account tier has been upgraded 🚀',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $tierNames = [
            'Email_Verified' => 'Email Verified',
            'Verified_And_Configured' => 'Verified & Configured',
            'Production' => 'Production',
            'Live' => 'Live',
        ];

        return new Content(
            markdown: 'mail.tier-advanced',
            with: [
                'user' => $this->user,
                'tierName' => $tierNames[$this->newTier] ?? $this->newTier,
                'nextSteps' => $this->getNextSteps(),
            ],
        );
    }

    private function getNextSteps(): array
    {
        return match ($this->newTier) {
            'Verified_And_Configured' => [
                'Your Apple and Google Wallet are now configured!',
                'You can request Production tier status for your account.',
                'Production status gives you access to advanced features and higher pass limits.',
            ],
            'Production' => [
                'Your account is now in Production tier.',
                'You can now create and distribute passes at higher volumes.',
                'Complete the pre-launch checklist to go live.',
            ],
            'Live' => [
                'Your account is now LIVE!',
                'You can distribute passes to all users.',
                'Monitor pass distribution through your analytics dashboard.',
            ],
            default => ['Your tier has been updated.'],
        };
    }
}
