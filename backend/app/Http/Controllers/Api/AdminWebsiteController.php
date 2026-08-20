<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Faq;
use App\Models\Review;
use App\Rules\PersonName;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWebsiteController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $imageOptimizationService)
    {
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => [
                'business' => $this->businessPayload($business),
                'reviews' => $business->reviews()->orderBy('sort_order')->orderBy('id')->get(),
                'faqs' => $business->faqs()->orderBy('sort_order')->orderBy('id')->get(),
            ],
        ]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'hero_title' => ['nullable', 'string', 'max:220'],
            'hero_text' => ['nullable', 'string', 'max:1200'],
            'about_title' => ['nullable', 'string', 'max:160'],
            'about_text' => ['nullable', 'string', 'max:4000'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2000'],
        ]);

        if (isset($validated['name'])) {
            $validated['logo_text'] = $this->monogram($validated['name']);
        }

        $business->update($validated);

        return response()->json(['data' => $this->businessPayload($business->fresh())]);
    }

    public function uploadLogo(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=8000,max_height=8000'],
        ]);

        $optimized = $this->imageOptimizationService->optimize(
            $request->file('logo'),
            'businesses',
            1000,
            1000,
            256,
            256,
        );

        $this->imageOptimizationService->delete(
            [$business->logo_path, $business->logo_thumbnail_path],
            'businesses',
        );

        $business->update([
            'logo_path' => $optimized['url'],
            'logo_thumbnail_path' => $optimized['thumbnail_url'],
        ]);

        return response()->json([
            'data' => $this->businessPayload($business->fresh()),
            'image' => [
                'format' => 'webp',
                'width' => $optimized['width'],
                'height' => $optimized['height'],
                'bytes' => $optimized['bytes'],
            ],
        ]);
    }

    public function deleteLogo(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $this->imageOptimizationService->delete(
            [$business->logo_path, $business->logo_thumbnail_path],
            'businesses',
        );
        $business->update(['logo_path' => null, 'logo_thumbnail_path' => null]);

        return response()->json(['data' => $this->businessPayload($business->fresh())]);
    }

    public function storeReview(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $review = $business->reviews()->create([
            ...$this->validatedReview($request),
            'source' => Review::SOURCE_MANUAL,
            'moderation_status' => Review::STATUS_APPROVED,
        ]);

        return response()->json(['data' => $review], 201);
    }

    public function updateReview(Request $request, Review $review): JsonResponse
    {
        $this->authorizeBusiness($request, $review->business);
        $validated = $this->validatedReview($request, true);
        $finalStatus = $validated['moderation_status'] ?? $review->moderation_status;
        if ($finalStatus !== Review::STATUS_APPROVED) {
            $validated['active'] = false;
        }
        $review->update($validated);

        return response()->json(['data' => $review->fresh()]);
    }

    public function destroyReview(Request $request, Review $review): JsonResponse
    {
        $this->authorizeBusiness($request, $review->business);
        $review->delete();

        return response()->json(['message' => 'Vélemény törölve.']);
    }

    public function storeFaq(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $faq = $business->faqs()->create($this->validatedFaq($request));

        return response()->json(['data' => $faq], 201);
    }

    public function updateFaq(Request $request, Faq $faq): JsonResponse
    {
        $this->authorizeBusiness($request, $faq->business);
        $faq->update($this->validatedFaq($request, true));

        return response()->json(['data' => $faq->fresh()]);
    }

    public function destroyFaq(Request $request, Faq $faq): JsonResponse
    {
        $this->authorizeBusiness($request, $faq->business);
        $faq->delete();

        return response()->json(['message' => 'GYIK elem törölve.']);
    }

    private function validatedReview(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'author' => [$required, 'string', new PersonName()],
            'text' => [$required, 'string', 'min:3', 'max:1200'],
            'rating' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:1', 'max:5'],
            'active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
            'sort_order' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:1000'],
            'moderation_status' => [
                'sometimes',
                Rule::in([Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_REJECTED]),
            ],
        ]);
    }

    private function validatedFaq(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'question' => [$required, 'string', 'max:255'],
            'answer' => [$required, 'string', 'max:3000'],
            'active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
            'sort_order' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    private function authorizeBusiness(Request $request, Business $business): void
    {
        abort_unless((int) $request->user()?->business_id === (int) $business->id, 403);
    }

    private function businessPayload(Business $business): array
    {
        return [
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
            'logoText' => $business->logo_text,
            'primaryColor' => $business->primary_color,
        ];
    }

    private function monogram(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $uppercaseMap = [
            'á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ö' => 'Ö',
            'ő' => 'Ő', 'ú' => 'Ú', 'ü' => 'Ü', 'ű' => 'Ű',
        ];
        $letters = [];

        foreach (array_filter($words) as $word) {
            if (preg_match('/^./u', $word, $match) === 1) {
                $letters[] = strtr(strtoupper($match[0]), $uppercaseMap);
            }
        }

        return implode('', array_slice($letters, 0, 2)) ?: 'IP';
    }
}
