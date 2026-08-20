<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Rules\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReviewController extends Controller
{
    public function store(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        $validated = $request->validate([
            'author' => ['required', 'string', new PersonName()],
            'email' => ['required', 'email:rfc', 'max:160'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['required', 'string', 'min:10', 'max:1200'],
            'legal_accepted' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ]);

        $review = $business->reviews()->create([
            'author' => trim($validated['author']),
            'text' => trim($validated['text']),
            'rating' => (int) $validated['rating'],
            'source' => Review::SOURCE_CUSTOMER,
            'moderation_status' => Review::STATUS_PENDING,
            'submitter_email' => mb_strtolower(trim($validated['email'])),
            'submitted_at' => now(),
            'legal_accepted_at' => now(),
            'active' => false,
            'sort_order' => (int) $business->reviews()->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Köszönjük! A véleményedet elküldtük, jóváhagyás után jelenhet meg.',
            'data' => [
                'id' => $review->id,
                'moderation_status' => $review->moderation_status,
            ],
        ], 201);
    }
}
