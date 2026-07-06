<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBookingController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['booked', 'cancelled', 'completed', 'no_show'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $business->bookings()
                ->with('service')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('date', $date))
                ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                    fn ($inner) => $inner
                        ->where('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_contact', 'like', "%{$q}%")
                ))
                ->latest('date')
                ->latest('start_time')
                ->limit(300)
                ->get(),
        ]);
    }

    public function summary(Business $business): JsonResponse
    {
        return response()->json([
            'data' => [
                'total' => $business->bookings()->count(),
                'active' => $business->bookings()->where('status', 'booked')->count(),
                'today' => $business->bookings()->whereDate('date', today())->where('status', 'booked')->count(),
                'cancelled' => $business->bookings()->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function block(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        $block = BlockedTime::create([
            ...$validated,
            'business_id' => $business->id,
        ]);

        return response()->json(['data' => $block], 201);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['booked', 'cancelled', 'completed', 'no_show'])],
        ]);

        $booking->update($validated);

        return response()->json(['data' => $booking]);
    }

    public function blockedTimes(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->blockedTimes()
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(100)
                ->get(),
        ]);
    }

    public function destroyBlock(BlockedTime $blockedTime): JsonResponse
    {
        $blockedTime->delete();

        return response()->json(['message' => 'Blokk torolve.']);
    }
}
