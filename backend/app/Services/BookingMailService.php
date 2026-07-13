<?php

namespace App\Services;

use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BookingMailService
{
    public function bookingCreated(Booking $booking): void
    {
        $this->sendForEvent($booking, 'booking_created');
    }

    public function bookingRescheduled(Booking $booking, array $previousSchedule): void
    {
        $this->sendForEvent($booking, 'booking_rescheduled', $previousSchedule);
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->sendForEvent($booking, 'booking_cancelled');
    }

    public function manageUrl(Booking $booking): string
    {
        return rtrim((string) config('appointment.public_url'), '/')
            .'/manage?token='.rawurlencode($booking->manage_token);
    }

    private function sendForEvent(Booking $booking, string $eventType, ?array $previousSchedule = null): void
    {
        $booking->loadMissing('business', 'service');
        $manageUrl = $this->manageUrl($booking);

        $recipients = [
            ['type' => 'customer', 'email' => $booking->customer_contact],
        ];

        $adminEmail = $booking->business?->email
            ?: $booking->business?->users()->where('role', 'owner')->value('email');

        if ($adminEmail) {
            $recipients[] = ['type' => 'admin', 'email' => $adminEmail];
        }

        foreach ($recipients as $recipient) {
            $this->sendOne(
                booking: $booking,
                eventType: $eventType,
                recipientType: $recipient['type'],
                recipientEmail: $recipient['email'],
                manageUrl: $manageUrl,
                previousSchedule: $previousSchedule,
            );
        }
    }

    private function sendOne(
        Booking $booking,
        string $eventType,
        string $recipientType,
        string $recipientEmail,
        string $manageUrl,
        ?array $previousSchedule = null,
    ): void {
        $mail = new BookingNotificationMail(
            booking: $booking,
            eventType: $eventType,
            recipientType: $recipientType,
            manageUrl: $manageUrl,
            previousSchedule: $previousSchedule,
        );

        $subject = $mail->subjectLine();

        try {
            Mail::to($recipientEmail)->send($mail);

            $this->writeLog([
                'business_id' => $booking->business_id,
                'booking_id' => $booking->id,
                'event_type' => $eventType,
                'recipient_type' => $recipientType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'status' => EmailLog::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->writeLog([
                'business_id' => $booking->business_id,
                'booking_id' => $booking->id,
                'event_type' => $eventType,
                'recipient_type' => $recipientType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'sent_at' => null,
            ]);

            report($exception);
        }
    }

    private function writeLog(array $payload): void
    {
        try {
            EmailLog::create($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
