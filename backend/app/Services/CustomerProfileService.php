<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CustomerAccount;
use App\Models\CustomerProfile;

class CustomerProfileService
{
    public function syncBooking(Booking $booking): Booking
    {
        $email = mb_strtolower(trim((string) $booking->customer_contact));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || str_contains($email, '@invalid.local')) {
            return $booking;
        }

        $profile = CustomerProfile::query()->firstOrCreate(
            ['business_id' => $booking->business_id, 'email' => $email],
            ['name' => $booking->customer_name, 'phone' => $booking->customer_phone],
        );

        $profileUpdates = [];
        if ($profile->name !== $booking->customer_name && $booking->customer_name !== 'Törölt ügyfél') {
            $profileUpdates['name'] = $booking->customer_name;
        }
        if ($booking->customer_phone && $profile->phone !== $booking->customer_phone) {
            $profileUpdates['phone'] = $booking->customer_phone;
        }
        if ($profileUpdates !== []) {
            $profile->update($profileUpdates);
        }

        $accountId = CustomerAccount::query()
            ->where('business_id', $booking->business_id)
            ->where('email', $email)
            ->value('id');

        $booking->forceFill([
            'customer_profile_id' => $profile->id,
            'customer_account_id' => $accountId,
        ])->save();

        return $booking->fresh(['business', 'service', 'customerProfile']);
    }

    public function attachHistoricalBookings(CustomerAccount $account): void
    {
        Booking::query()
            ->where('business_id', $account->business_id)
            ->whereRaw('LOWER(customer_contact) = ?', [$account->email])
            ->whereNull('anonymized_at')
            ->update(['customer_account_id' => $account->id]);

        $profile = CustomerProfile::query()->firstOrCreate(
            ['business_id' => $account->business_id, 'email' => $account->email],
            ['name' => $account->name, 'phone' => $account->phone],
        );

        $updates = [];
        if ($account->name && $profile->name !== $account->name) $updates['name'] = $account->name;
        if ($account->phone && $profile->phone !== $account->phone) $updates['phone'] = $account->phone;
        if ($updates !== []) $profile->update($updates);
    }
}
