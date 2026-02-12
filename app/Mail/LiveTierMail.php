<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LiveTierMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PassKit Account is Now LIVE! 🎊',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.live-tier',
            with: [
                'user' => $this->user,
                'features' => [
                    'Unlimited pass distribution',
                    'Advanced analytics dashboard',
                    'Custom domains',
                    'API access',
                    'Webhook support',
                    '24/7 premium support',
                ],
            ],
        );
    }
}
