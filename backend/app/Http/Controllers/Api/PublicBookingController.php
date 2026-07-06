<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicBookingController extends Controller
{
    public function __construct(private readonly SlotService $slotService)
    {
    }

    public function business(Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        return response()->json([
            'data' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'tagline' => $business->tagline,
                'timezone' => $business->timezone,
                'primaryColor' => $business->primary_color,
                'logoText' => $business->logo_text,
            ],
        ]);
    }

    public function services(Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        return response()->json([
            'data' => $business->services()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function slots(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::where('business_id', $business->id)->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->slotsFor($business, $service, $validated['date']),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_contact' => ['required', 'string', 'max:160'],
            'customer_note' => ['nullable', 'string', 'max:800'],
        ]);

        $service = Service::where('business_id', $business->id)->findOrFail($validated['service_id']);

        return DB::transaction(function () use ($business, $service, $validated): JsonResponse {
            $slot = collect($this->slotService->slotsFor($business, $service, $validated['date']))
                ->firstWhere('time', $validated['time']);

            if (! $slot) {
                return response()->json(['message' => 'The selected slot is no longer available.'], 409);
            }

            $booking = Booking::create([
                'business_id' => $business->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'date' => $validated['date'],
                'start_time' => $slot['time'],
                'end_time' => $slot['endTime'],
                'busy_until' => $slot['busyUntil'],
                'customer_name' => $validated['customer_name'],
                'customer_contact' => $validated['customer_contact'],
                'customer_note' => $validated['customer_note'] ?? null,
                'manage_token' => Str::random(48),
                'status' => 'booked',
            ]);

            return response()->json([
                'data' => $booking,
                'manageUrl' => '/manage/'.$booking->manage_token,
            ], 201);
        });
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json(['data' => $booking->load('business', 'service')]);
    }

    public function cancel(Booking $booking): JsonResponse
    {
        if ($booking->status !== 'booked') {
            return response()->json(['message' => 'Booking is not active.'], 409);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json(['data' => $booking]);
    }

    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        if ($booking->status !== 'booked') {
            return response()->json(['message' => 'Booking is not active.'], 409);
        }

        $business = $booking->business;
        $service = $booking->service;
        $slot = collect($this->slotService->slotsFor($business, $service, $validated['date'], $booking->id))
            ->firstWhere('time', $validated['time']);

        if (! $slot) {
            return response()->json(['message' => 'The selected slot is no longer available.'], 409);
        }

        $booking->update([
            'date' => $validated['date'],
            'start_time' => $slot['time'],
            'end_time' => $slot['endTime'],
            'busy_until' => $slot['busyUntil'],
        ]);

        return response()->json(['data' => $booking->fresh()]);
    }
}
