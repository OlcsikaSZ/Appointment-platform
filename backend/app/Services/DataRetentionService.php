<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataRetentionService
{
    public function purgeAll(): array
    {
        $result = ['businesses' => 0, 'bookings_anonymized' => 0, 'email_logs_deleted' => 0, 'tokens_expired' => 0];

        Business::query()->each(function (Business $business) use (&$result): void {
            $stats = $this->purgeBusiness($business);
            $result['businesses']++;
            $result['bookings_anonymized'] += $stats['bookings_anonymized'];
            $result['email_logs_deleted'] += $stats['email_logs_deleted'];
            $result['tokens_expired'] += $stats['tokens_expired'];
        });

        return $result;
    }

    public function purgeBusiness(Business $business): array
    {
        $timezone = $business->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $bookingCutoff = $today->subDays(max(1, (int) ($business->booking_retention_days ?: 730)))->toDateString();
        $emailCutoff = CarbonImmutable::now($timezone)->subDays(max(1, (int) ($business->email_log_retention_days ?: 180)));
        $tokenCutoff = $today->subDays(max(1, (int) ($business->manage_token_retention_days ?: 30)))->toDateString();
        $expiredAt = now()->subSecond();

        return DB::transaction(function () use ($business, $bookingCutoff, $emailCutoff, $tokenCutoff, $expiredAt): array {
            $emailDeleted = $business->emailLogs()->where('created_at', '<', $emailCutoff)->delete();

            $expiredTokens = $business->bookings()
                ->whereDate('date', '<', $tokenCutoff)
                ->where(function ($query) use ($expiredAt): void {
                    $query->whereNull('manage_token_expires_at')->orWhere('manage_token_expires_at', '>', $expiredAt);
                })
                ->update(['manage_token_expires_at' => $expiredAt]);

            $anonymized = 0;
            $business->bookings()
                ->whereDate('date', '<', $bookingCutoff)
                ->whereNull('anonymized_at')
                ->orderBy('id')
                ->chunkById(100, function ($bookings) use (&$anonymized): void {
                    foreach ($bookings as $booking) {
                        $this->anonymizeBooking($booking);
                        $anonymized++;
                    }
                });

            return [
                'bookings_anonymized' => $anonymized,
                'email_logs_deleted' => $emailDeleted,
                'tokens_expired' => $expiredTokens,
            ];
        });
    }

    public function anonymizeBooking(Booking $booking): Booking
    {
        if ($booking->anonymized_at) {
            return $booking;
        }

        DB::transaction(function () use ($booking): void {
            $booking->emailLogs()->update([
                'recipient_email' => 'deleted+booking-'.$booking->id.'@invalid.local',
                'payload' => null,
            ]);

            $booking->forceFill([
                'customer_name' => 'Törölt ügyfél',
                'customer_contact' => 'deleted+'.$booking->id.'@invalid.local',
                'customer_phone' => null,
                'customer_note' => null,
                'customer_profile_id' => null,
                'customer_account_id' => null,
                'manage_token' => Str::random(64),
                'manage_token_expires_at' => now()->subSecond(),
                'anonymized_at' => now(),
            ])->save();
        });

        return $booking->fresh();
    }
}
