<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminSecurityNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public string $title,
        public array $lines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title.' – '.$this->business->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-security-notification');
    }
}
