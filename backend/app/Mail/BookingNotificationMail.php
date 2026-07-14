<?php

namespace App\Mail;

use App\Models\EmailSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data,
        public string $eventType,
        public string $recipientType,
        public array $settings,
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = trim((string) config('mail.from.address'));
        $senderName = trim((string) ($this->settings['sender_name'] ?? ''))
            ?: (string) ($this->data['business_name'] ?? config('mail.from.name', 'Időpontfoglalás'));

        $replyToAddress = trim((string) ($this->settings['reply_to'] ?? ''))
            ?: trim((string) ($this->data['business_email'] ?? ''));

        return new Envelope(
            from: $fromAddress !== '' ? new Address($fromAddress, $senderName) : null,
            replyTo: filter_var($replyToAddress, FILTER_VALIDATE_EMAIL)
                ? [new Address($replyToAddress, (string) ($this->data['business_name'] ?? $senderName))]
                : [],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        $view = $this->recipientType === 'admin'
            ? 'emails.booking-notification-admin'
            : 'emails.booking-notification-customer';

        return new Content(
            view: $view,
            with: [
                'emailData' => $this->data,
                'eventLabel' => $this->eventLabel(),
                'subjectLine' => $this->subjectLine(),
                'introText' => $this->introText(),
                'footerText' => (string) ($this->settings['footer_text'] ?? ''),
            ],
        );
    }

    public function subjectLine(): string
    {
        $template = $this->templateSettings()['subject'] ?? $this->fallbackSubject();

        return $this->renderTemplate((string) $template);
    }

    public function introText(): string
    {
        $template = $this->templateSettings()['intro'] ?? '';

        return $this->renderTemplate((string) $template);
    }

    public function eventLabel(): string
    {
        if ($this->recipientType === 'admin') {
            return match ($this->eventType) {
                'booking_created' => 'Új foglalás',
                'booking_rescheduled' => 'Foglalás módosítva',
                'booking_cancelled' => 'Foglalás lemondva',
                default => 'Foglalási értesítés',
            };
        }

        return match ($this->eventType) {
            'booking_created' => 'Foglalás rögzítve',
            'booking_rescheduled' => 'Időpont módosítva',
            'booking_cancelled' => 'Foglalás lemondva',
            default => 'Foglalási értesítés',
        };
    }

    private function templateSettings(): array
    {
        return $this->settings['templates'][$this->recipientType][$this->eventType]
            ?? EmailSetting::defaults()['templates'][$this->recipientType][$this->eventType]
            ?? [];
    }

    private function fallbackSubject(): string
    {
        return match ($this->eventType) {
            'booking_created' => 'Foglalás visszaigazolása – {business_name}',
            'booking_rescheduled' => 'Időpont módosítva – {business_name}',
            'booking_cancelled' => 'Foglalás lemondva – {business_name}',
            default => 'Foglalási értesítés – {business_name}',
        };
    }

    private function renderTemplate(string $template): string
    {
        $replacements = [
            '{business_name}' => (string) ($this->data['business_name'] ?? 'Időpontfoglalás'),
            '{customer_name}' => (string) ($this->data['customer_name'] ?? ''),
            '{customer_email}' => (string) ($this->data['customer_email'] ?? ''),
            '{service_name}' => (string) ($this->data['service_name'] ?? ''),
            '{date}' => (string) ($this->data['date_formatted'] ?? $this->data['date'] ?? ''),
            '{time}' => trim((string) ($this->data['start_time'] ?? '')).'–'.trim((string) ($this->data['end_time'] ?? '')),
            '{manage_url}' => (string) ($this->data['manage_url'] ?? ''),
        ];

        return strtr($template, $replacements);
    }
}
