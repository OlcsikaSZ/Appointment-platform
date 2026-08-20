<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\LegalDocumentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    use AuthorizesBusinessAccess;

    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $this->payload($business),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request, Business $business, LegalDocumentSanitizer $sanitizer): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'min_advance_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:730'],
            'slot_interval_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])],
            'cancellation_deadline_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'reschedule_deadline_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'reminder_24h_enabled' => ['sometimes', 'boolean'],
            'reminder_2h_enabled' => ['sometimes', 'boolean'],
            'timezone' => ['required', 'timezone'],
            'hide_prices' => ['required', 'boolean'],
            'booking_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'email_log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'manage_token_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'privacy_policy' => ['nullable', 'string', 'max:50000'],
            'terms_text' => ['nullable', 'string', 'max:50000'],
            'imprint_text' => ['nullable', 'string', 'max:50000'],
            'cookie_policy' => ['nullable', 'string', 'max:50000'],
        ]);

        foreach (['privacy_policy', 'terms_text', 'imprint_text', 'cookie_policy'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = $sanitizer->sanitize($validated[$field]);
            }
        }

        $business->update($validated);

        return response()->json(['data' => $this->payload($business->fresh())]);
    }

    private function payload(Business $business): array
    {
        $sanitizer = app(LegalDocumentSanitizer::class);
        return [
            'min_advance_minutes' => (int) ($business->min_advance_minutes ?? 60),
            'max_advance_days' => (int) ($business->max_advance_days ?? 90),
            'slot_interval_minutes' => (int) ($business->slot_interval_minutes ?? 15),
            'cancellation_deadline_minutes' => (int) ($business->cancellation_deadline_minutes ?? 1440),
            'reschedule_deadline_minutes' => (int) ($business->reschedule_deadline_minutes ?? 1440),
            'reminder_24h_enabled' => (bool) ($business->reminder_24h_enabled ?? true),
            'reminder_2h_enabled' => (bool) ($business->reminder_2h_enabled ?? false),
            'timezone' => $business->timezone ?: 'Europe/Budapest',
            'hide_prices' => (bool) ($business->hide_prices ?? false),
            'booking_retention_days' => (int) ($business->booking_retention_days ?? 730),
            'email_log_retention_days' => (int) ($business->email_log_retention_days ?? 180),
            'manage_token_retention_days' => (int) ($business->manage_token_retention_days ?? 30),
            'privacy_policy' => $sanitizer->sanitize($business->privacy_policy),
            'terms_text' => $sanitizer->sanitize($business->terms_text),
            'imprint_text' => $sanitizer->sanitize($business->imprint_text),
            'cookie_policy' => $sanitizer->sanitize($business->cookie_policy),
        ];
    }
}
