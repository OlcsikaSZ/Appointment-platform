<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingRuleService
{
    public function appointmentAt(Booking $booking): CarbonImmutable
    {
        $timezone = $booking->business?->timezone ?: config('app.timezone');

        return CarbonImmutable::parse(
            $booking->date->format('Y-m-d').' '.substr((string) $booking->start_time, 0, 5),
            $timezone,
        );
    }

    public function manageTokenExpiresAt(Business $business, string $bookingDate): CarbonImmutable
    {
        $days = max(1, (int) ($business->manage_token_retention_days ?: 30));

        return CarbonImmutable::parse($bookingDate, $business->timezone)
            ->endOfDay()
            ->addDays($days);
    }

    public function ensureManageTokenValid(Booking $booking): void
    {
        if ($booking->anonymized_at) {
            throw new HttpException(410, 'A foglalás személyes adatait már töröltük, a kezelőlink nem használható.');
        }

        if ($booking->manage_token_expires_at && $booking->manage_token_expires_at->isPast()) {
            throw new HttpException(410, 'A foglalás kezelőlinkje lejárt. Vedd fel a kapcsolatot a szolgáltatóval.');
        }
    }

    public function canCancel(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_BOOKED) {
            return false;
        }

        $minutes = max(0, (int) ($booking->business?->cancellation_deadline_minutes ?? 1440));

        return CarbonImmutable::now($booking->business?->timezone ?: config('app.timezone'))
            ->lessThanOrEqualTo($this->appointmentAt($booking)->subMinutes($minutes));
    }

    public function canReschedule(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_BOOKED) {
            return false;
        }

        $minutes = max(0, (int) ($booking->business?->reschedule_deadline_minutes ?? 1440));

        return CarbonImmutable::now($booking->business?->timezone ?: config('app.timezone'))
            ->lessThanOrEqualTo($this->appointmentAt($booking)->subMinutes($minutes));
    }

    public function managePayload(Booking $booking): array
    {
        $business = $booking->business;
        $cancelMinutes = max(0, (int) ($business?->cancellation_deadline_minutes ?? 1440));
        $rescheduleMinutes = max(0, (int) ($business?->reschedule_deadline_minutes ?? 1440));

        return [
            'can_cancel' => $this->canCancel($booking),
            'can_reschedule' => $this->canReschedule($booking),
            'cancel_deadline_at' => $this->appointmentAt($booking)->subMinutes($cancelMinutes)->toIso8601String(),
            'reschedule_deadline_at' => $this->appointmentAt($booking)->subMinutes($rescheduleMinutes)->toIso8601String(),
            'manage_token_expires_at' => $booking->manage_token_expires_at?->toIso8601String(),
            'slot_interval_minutes' => max(5, (int) ($business?->slot_interval_minutes ?? 15)),
        ];
    }
}
