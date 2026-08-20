<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Rules\PersonName;
use App\Services\BookingDayLockService;
use App\Services\BookingMailService;
use App\Services\BookingRuleService;
use App\Services\CalendarInviteService;
use App\Services\SlotService;
use App\Services\CustomerProfileService;
use App\Services\LegalDocumentSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicBookingController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService,
        private readonly BookingMailService $bookingMailService,
        private readonly BookingDayLockService $bookingDayLockService,
        private readonly BookingRuleService $bookingRuleService,
        private readonly CustomerProfileService $customerProfileService,
        private readonly LegalDocumentSanitizer $legalDocumentSanitizer,
        private readonly CalendarInviteService $calendarInviteService,
    ) {
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
                'heroTitle' => $business->hero_title,
                'heroText' => $business->hero_text,
                'aboutTitle' => $business->about_title,
                'aboutText' => $business->about_text,
                'phone' => $business->phone,
                'email' => $business->email,
                'address' => $business->address,
                'openingHours' => $business->opening_hours,
                'googleMapsUrl' => $business->google_maps_url,
                'logoUrl' => $business->logo_path,
                'logoThumbnailUrl' => $business->logo_thumbnail_path,
                'timezone' => $business->timezone,
                'primaryColor' => $business->primary_color,
                'logoText' => $business->logo_text,
                'hidePrices' => (bool) ($business->hide_prices ?? false),
                'bookingRules' => [
                    'minAdvanceMinutes' => (int) ($business->min_advance_minutes ?? 60),
                    'maxAdvanceDays' => (int) ($business->max_advance_days ?? 90),
                    'slotIntervalMinutes' => (int) ($business->slot_interval_minutes ?? 15),
                    'cancellationDeadlineMinutes' => (int) ($business->cancellation_deadline_minutes ?? 1440),
                    'rescheduleDeadlineMinutes' => (int) ($business->reschedule_deadline_minutes ?? 1440),
                ],
                'legal' => [
                    'privacyPolicy' => $this->legalDocumentSanitizer->sanitize($business->privacy_policy),
                    'termsText' => $this->legalDocumentSanitizer->sanitize($business->terms_text),
                    'imprintText' => $this->legalDocumentSanitizer->sanitize($business->imprint_text),
                    'cookiePolicy' => $this->legalDocumentSanitizer->sanitize($business->cookie_policy),
                ],
                'reviews' => $business->reviews()
                    ->where('active', true)
                    ->where('moderation_status', \App\Models\Review::STATUS_APPROVED)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'author', 'text', 'rating']),
                'faqs' => $business->faqs()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'question', 'answer']),
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
        abort_unless($business->active, 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->slotsFor($business, $service, $validated['date']),
        ]);
    }

    /**
     * Nyilvános napi elérhetőség a naptáras foglalóhoz.
     * Ügyféladatot nem ad vissza: csak a szabad slotokat és az adott napi nyitvatartást.
     */
    public function availability(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::where('business_id', $business->id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        $day = \Carbon\CarbonImmutable::parse($validated['date'], $business->timezone)->startOfDay();
        $weekday = (int) $day->dayOfWeek;

        return response()->json([
            'data' => [
                'date' => $validated['date'],
                'slots' => $this->slotService->slotsFor($business, $service, $validated['date']),
                'workingHours' => $business->workingHours()
                    ->where('weekday', $weekday)
                    ->orderBy('start_time')
                    ->get(['start_time', 'end_time']),
            ],
        ]);
    }

    public function availabilityCalendar(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        $start = \Carbon\CarbonImmutable::parse($validated['start'], $business->timezone)->startOfDay();
        $end = \Carbon\CarbonImmutable::parse($validated['end'], $business->timezone)->startOfDay();
        abort_if($start->greaterThan($end), 422, 'A kezdő dátum nem lehet későbbi a záró dátumnál.');
        abort_if($start->diffInDays($end) > 62, 422, 'Legfeljebb 63 nap kérhető le egyszerre.');

        $service = Service::where('business_id', $business->id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->availabilitySummaryForRange(
                $business,
                $service,
                $validated['start'],
                $validated['end'],
            ),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', new PersonName()],
            'customer_contact' => ['required', 'string', 'email:rfc', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_note' => ['nullable', 'string', 'min:3', 'max:800'],
            'legal_accepted' => ['required', 'accepted'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        $createdBooking = null;

        $response = $this->bookingDayLockService->run($business, $validated['date'], function () use ($business, $service, $validated, &$createdBooking): JsonResponse {
                $slot = collect($this->slotService->slotsFor($business, $service, $validated['date']))
                    ->firstWhere('time', $validated['time']);

                if (! $slot) {
                    return response()->json(['message' => 'A kiválasztott időpont már nem elérhető.'], 409);
                }

                try {
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
                        'customer_phone' => $validated['customer_phone'] ?? null,
                        'customer_note' => $validated['customer_note'] ?? null,
                        'manage_token' => Str::random(64),
                        'manage_token_expires_at' => $this->bookingRuleService->manageTokenExpiresAt($business, $validated['date']),
                        'status' => Booking::STATUS_BOOKED,
                        'legal_accepted_at' => now(),
                        'legal_text_hash' => hash(
                            'sha256',
                            (string) $business->privacy_policy."\n---\n".(string) $business->terms_text,
                        ),
                    ]);
                } catch (QueryException $exception) {
                    if ($this->isDuplicateActiveSlot($exception)) {
                        return response()->json(['message' => 'A kiválasztott időpont közben foglalttá vált.'], 409);
                    }

                    throw $exception;
                }

                $createdBooking = $this->customerProfileService->syncBooking(
                    $booking->fresh(['business', 'service'])
                );

            return response()->json([
                'data' => $booking,
                'manageUrl' => $this->bookingMailService->manageUrl($booking),
            ], 201);
        });

        if ($createdBooking) {
            $this->bookingMailService->bookingCreated($createdBooking);
        }

        return $response;
    }

    public function show(Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);

        return response()->json([
            'data' => $booking,
            'business' => [
                'name' => $booking->business->name,
                'logoUrl' => $booking->business->logo_path,
                'logoThumbnailUrl' => $booking->business->logo_thumbnail_path,
                'logoText' => $booking->business->logo_text,
            ],
            'manage' => $this->bookingRuleService->managePayload($booking),
            'legal' => [
                'privacyPolicy' => $this->legalDocumentSanitizer->sanitize($booking->business->privacy_policy),
                'termsText' => $this->legalDocumentSanitizer->sanitize($booking->business->terms_text),
                'imprintText' => $this->legalDocumentSanitizer->sanitize($booking->business->imprint_text),
                'cookiePolicy' => $this->legalDocumentSanitizer->sanitize($booking->business->cookie_policy),
            ],
        ]);
    }

    public function calendar(Booking $booking): Response
    {
        $booking->loadMissing('business');
        $this->bookingRuleService->ensureManageTokenValid($booking);

        return response($this->calendarInviteService->build($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->calendarInviteService->filename($booking).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function manageSlots(Request $request, Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        return response()->json([
            'data' => $this->slotService->slotsFor(
                $booking->business,
                $booking->service,
                $validated['date'],
                $booking->id
            ),
        ]);
    }

    public function manageAvailability(Request $request, Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        $business = $booking->business;
        $service = $booking->service;
        $day = \Carbon\CarbonImmutable::parse($validated['date'], $business->timezone)->startOfDay();
        $weekday = (int) $day->dayOfWeek;

        return response()->json([
            'data' => [
                'date' => $validated['date'],
                'slots' => $this->slotService->slotsFor($business, $service, $validated['date'], $booking->id),
                'workingHours' => $business->workingHours()
                    ->where('weekday', $weekday)
                    ->orderBy('start_time')
                    ->get(['start_time', 'end_time']),
            ],
        ]);
    }

    public function manageAvailabilityCalendar(Request $request, Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);
        $validated = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        $business = $booking->business;
        $start = \Carbon\CarbonImmutable::parse($validated['start'], $business->timezone)->startOfDay();
        $end = \Carbon\CarbonImmutable::parse($validated['end'], $business->timezone)->startOfDay();
        abort_if($start->greaterThan($end), 422, 'A kezdő dátum nem lehet későbbi a záró dátumnál.');
        abort_if($start->diffInDays($end) > 62, 422, 'Legfeljebb 63 nap kérhető le egyszerre.');

        return response()->json([
            'data' => $this->slotService->availabilitySummaryForRange(
                $business,
                $booking->service,
                $validated['start'],
                $validated['end'],
                $booking->id,
            ),
        ]);
    }

    public function cancel(Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);
        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        if (! $this->bookingRuleService->canCancel($booking)) {
            return response()->json(['message' => 'A lemondási határidő lejárt. Vedd fel a kapcsolatot a szolgáltatóval.'], 422);
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $fresh = $booking->fresh(['business', 'service']);
        $this->bookingMailService->bookingCancelled($fresh);

        return response()->json(['data' => $fresh]);
    }

    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $booking->loadMissing('business', 'service');
        $this->bookingRuleService->ensureManageTokenValid($booking);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        if (! $this->bookingRuleService->canReschedule($booking)) {
            return response()->json(['message' => 'A módosítási határidő lejárt. Vedd fel a kapcsolatot a szolgáltatóval.'], 422);
        }

        $business = $booking->business;
        $service = $booking->service;
        $previousSchedule = [
            'date' => $booking->date->format('Y-m-d'),
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
        ];

        if ($validated['date'] === $previousSchedule['date']
            && $validated['time'] === substr($previousSchedule['start_time'], 0, 5)) {
            return response()->json(['message' => 'Az új időpont megegyezik a jelenlegi foglalással.'], 422);
        }

        $updatedBooking = null;

        $response = $this->bookingDayLockService->run(
            $business,
            [$previousSchedule['date'], $validated['date']],
            function () use ($booking, $business, $service, $validated, &$updatedBooking): JsonResponse {
                $slot = collect($this->slotService->slotsFor($business, $service, $validated['date'], $booking->id))
                    ->firstWhere('time', $validated['time']);

                if (! $slot) {
                    return response()->json(['message' => 'A kiválasztott időpont már nem elérhető.'], 409);
                }

                try {
                    $booking->update([
                        'date' => $validated['date'],
                        'start_time' => $slot['time'],
                        'end_time' => $slot['endTime'],
                        'busy_until' => $slot['busyUntil'],
                        'manage_token_expires_at' => $this->bookingRuleService->manageTokenExpiresAt($business, $validated['date']),
                        'status' => Booking::STATUS_BOOKED,
                    ]);
                } catch (QueryException $exception) {
                    if ($this->isDuplicateActiveSlot($exception)) {
                        return response()->json(['message' => 'A kiválasztott időpont közben foglalttá vált.'], 409);
                    }

                    throw $exception;
                }

                $updatedBooking = $booking->fresh(['business', 'service']);

            return response()->json(['data' => $updatedBooking]);
        });

        if ($updatedBooking) {
            $this->bookingMailService->bookingRescheduled($updatedBooking, $previousSchedule);
        }

        return $response;
    }

    private function isDuplicateActiveSlot(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($errorInfo[1] ?? 0);

        return $sqlState === '23000'
            && (
                $driverCode === 1062
                || str_contains($exception->getMessage(), 'bookings_active_slot_key_unique')
            );
    }
}
