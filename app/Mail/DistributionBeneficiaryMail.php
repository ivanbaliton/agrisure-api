<?php

namespace App\Mail;

use App\Models\DistributionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DistributionBeneficiaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $event;
    public $farmerName;

    /**
     * Create a new message instance.
     */
    public function __construct(DistributionEvent $event, string $farmerName)
    {
        $this->event = $event;
        $this->farmerName = $farmerName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $title = $this->event->title ?? $this->event->name ?? 'Farm Supply Assistance';
        
        return new Envelope(
            subject: "🌾 AgriSure: Notice of Distribution for {$title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.distribution-beneficiary', // Points to a clean Markdown template
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}