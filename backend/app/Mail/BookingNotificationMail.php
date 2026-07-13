<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $eventType,
        public string $recipientType,
        public string $manageUrl,
        public ?array $previousSchedule = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
            with: [
                'eventLabel' => $this->eventLabel(),
                'subjectLine' => $this->subjectLine(),
            ],
        );
    }

    public function subjectLine(): string
    {
        $business = $this->booking->business?->name ?: 'Időpontfoglalás';

        return match ($this->eventType) {
            'booking_created' => "Foglalás visszaigazolása – {$business}",
            'booking_rescheduled' => "Időpont módosítva – {$business}",
            'booking_cancelled' => "Foglalás lemondva – {$business}",
            default => "Foglalási értesítés – {$business}",
        };
    }

    public function eventLabel(): string
    {
        return match ($this->eventType) {
            'booking_created' => 'Foglalás rögzítve',
            'booking_rescheduled' => 'Időpont módosítva',
            'booking_cancelled' => 'Foglalás lemondva',
            default => 'Foglalási értesítés',
        };
    }
}
