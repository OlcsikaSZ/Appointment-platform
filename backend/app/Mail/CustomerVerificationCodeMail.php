<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerVerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public string $code,
        public string $purpose,
        public int $validMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'registration'
            ? 'Regisztráció megerősítése – '.$this->business->name
            : 'Új jelszó beállítása – '.$this->business->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-verification-code');
    }
}
