<?php

namespace App\Mail;

use App\Models\CustomerAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerMagicLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomerAccount $account,
        public string $loginUrl,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Belépési link – '.$this->account->business->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-magic-link',
            with: ['account' => $this->account, 'loginUrl' => $this->loginUrl],
        );
    }
}
