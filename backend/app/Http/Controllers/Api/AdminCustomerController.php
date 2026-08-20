<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    use AuthorizesBusinessAccess;

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:160']]);

        $profiles = $business->customerProfiles()
            ->withCount([
                'bookings',
                'bookings as no_show_count' => fn ($query) => $query->where('status', Booking::STATUS_NO_SHOW),
                'bookings as active_count' => fn ($query) => $query->where('status', Booking::STATUS_BOOKED),
            ])
            ->when($validated['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at')
            ->limit(250)
            ->get();

        $registeredEmails = $business->customerAccounts()
            ->whereNotNull('email_verified_at')
            ->pluck('email')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->flip();

        $profiles->each(function (CustomerProfile $profile) use ($registeredEmails): void {
            $profile->setAttribute(
                'registered_account',
                $registeredEmails->has(mb_strtolower(trim($profile->email))),
            );
        });

        return response()->json(['data' => $profiles]);
    }

    public function show(Request $request, CustomerProfile $customerProfile): JsonResponse
    {
        $this->authorizeBusinessId($request, (int) $customerProfile->business_id);

        $customerProfile->loadCount([
            'bookings',
            'bookings as no_show_count' => fn ($query) => $query->where('status', Booking::STATUS_NO_SHOW),
            'bookings as completed_count' => fn ($query) => $query->where('status', Booking::STATUS_COMPLETED),
            'bookings as cancelled_count' => fn ($query) => $query->where('status', Booking::STATUS_CANCELLED),
        ]);

        $customerProfile->setAttribute('registered_account', CustomerProfile::query()
            ->whereKey($customerProfile->id)
            ->whereExists(function ($query) use ($customerProfile): void {
                $query->selectRaw('1')
                    ->from('customer_accounts')
                    ->whereColumn('customer_accounts.business_id', 'customer_profiles.business_id')
                    ->whereColumn('customer_accounts.email', 'customer_profiles.email')
                    ->whereNotNull('customer_accounts.email_verified_at');
            })
            ->exists());

        return response()->json([
            'data' => $customerProfile,
            'bookings' => $customerProfile->bookings()
                ->with('service:id,name,active')
                ->orderByDesc('date')
                ->orderByDesc('start_time')
                ->limit(200)
                ->get(),
        ]);
    }

    public function update(Request $request, CustomerProfile $customerProfile): JsonResponse
    {
        $this->authorizeBusinessId($request, (int) $customerProfile->business_id);
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $customerProfile->update($validated);

        return response()->json(['data' => $customerProfile->fresh()]);
    }
}
