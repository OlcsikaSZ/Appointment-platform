<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;

class CalendarInviteService
{
    public function __construct(private readonly BookingMailService $bookingMailService)
    {
    }

    public function build(Booking $booking): string
    {
        $booking->loadMissing('business');

        $timezone = $booking->business?->timezone ?: 'Europe/Budapest';
        $date = $booking->date instanceof \DateTimeInterface
            ? $booking->date->format('Y-m-d')
            : (string) $booking->date;
        $start = $this->utcDateTime($date, (string) $booking->start_time, $timezone);
        $end = $this->utcDateTime($date, (string) $booking->end_time, $timezone);
        $manageUrl = $this->bookingMailService->manageUrl($booking);
        $description = 'Foglalás kezelése: '.$manageUrl;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'appointment.local';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'PRODID:-//Idovonal//Foglalas//HU',
            'BEGIN:VEVENT',
            sprintf('UID:booking-%s@%s', $booking->getKey(), $host),
            'DTSTAMP:'.($booking->created_at ?: now())->copy()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start,
            'DTEND:'.$end,
            'SUMMARY:'.$this->escape($booking->service_name),
            'DESCRIPTION:'.$this->escape($description),
            ...($booking->business?->address
                ? ['LOCATION:'.$this->escape($booking->business->address)]
                : []),
            'URL:'.$manageUrl,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    public function filename(Booking $booking): string
    {
        $date = $booking->date instanceof \DateTimeInterface
            ? $booking->date->format('Y-m-d')
            : (string) $booking->date;

        return "foglalas-{$date}.ics";
    }

    private function utcDateTime(string $date, string $time, string $timezone): string
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date.' '.substr($time, 0, 5).':00',
            $timezone,
        )->utc()->format('Ymd\THis\Z');
    }

    private function escape(mixed $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\r", "\n", ';', ','],
            ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'],
            (string) ($value ?? ''),
        );
    }

    private function fold(string $line): string
    {
        $parts = [];
        $remaining = $line;
        $limit = 75;

        while (strlen($remaining) > $limit) {
            $cut = $limit;
            while ($cut > 0 && (ord($remaining[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $parts[] = substr($remaining, 0, $cut);
            $remaining = substr($remaining, $cut);
            $limit = 74;
        }

        $parts[] = $remaining;

        return implode("\r\n ", $parts);
    }
}
