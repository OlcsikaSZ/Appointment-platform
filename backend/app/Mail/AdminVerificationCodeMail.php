<?php

namespace App\Mail;

use App\Models\AdminVerificationCode;
use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminVerificationCodeMail extends Mailable implements ShouldQueue
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
        $subject = match ($this->purpose) {
            AdminVerificationCode::PURPOSE_OWNER_ACTIVATION => 'Tulajdonosi fiók aktiválása',
            AdminVerificationCode::PURPOSE_EMAIL_CHANGE => 'Új admin e-mail-cím megerősítése',
            default => 'Admin jelszó visszaállítása',
        };

        return new Envelope(subject: $subject.' – '.$this->business->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-verification-code');
    }
}
