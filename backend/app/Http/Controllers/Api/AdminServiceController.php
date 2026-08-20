<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Service;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceController extends Controller
{
    use AuthorizesBusinessAccess;

    public function __construct(private readonly ImageOptimizationService $imageOptimizationService)
    {
    }

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $business->services()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $this->validatedService($request);
        $service = $business->services()->create($validated);

        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);
        $validated = $this->validatedService($request, true);
        $service->update($validated);

        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        if ($service->bookings()->exists()) {
            $service->update(['active' => false]);

            return response()->json([
                'message' => 'A szolgáltatáshoz már van foglalás, ezért törlés helyett inaktívra állítottuk.',
                'data' => $service->fresh(),
            ]);
        }

        $this->imageOptimizationService->delete(
            [$service->image_url, $service->image_thumbnail_url],
            'services',
        );
        $service->delete();

        return response()->json(['message' => 'Szolgáltatás törölve.']);
    }

    public function reorder(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        foreach ($validated['items'] as $item) {
            Service::where('business_id', $business->id)
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return $this->index($request, $business);
    }

    /**
     * Valódi képfeltöltés a szolgáltatáshoz. A fájl a backend publikus storage könyvtárába kerül,
     * a frontend pedig a meglévő /uploads rewrite-on keresztül éri el.
     */
    public function uploadImage(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ]);

        $optimized = $this->imageOptimizationService->optimize(
            $request->file('image'),
            'services',
            1600,
            1200,
            480,
            320,
        );

        $this->imageOptimizationService->delete(
            [$service->image_url, $service->image_thumbnail_url],
            'services',
        );

        $service->update([
            'image_url' => $optimized['url'],
            'image_thumbnail_url' => $optimized['thumbnail_url'],
        ]);

        return response()->json([
            'data' => $service->fresh(),
            'image' => [
                'format' => 'webp',
                'width' => $optimized['width'],
                'height' => $optimized['height'],
                'bytes' => $optimized['bytes'],
            ],
        ]);
    }

    public function deleteImage(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);
        $this->imageOptimizationService->delete(
            [$service->image_url, $service->image_thumbnail_url],
            'services',
        );
        $service->update(['image_url' => null, 'image_thumbnail_url' => null]);

        return response()->json(['data' => $service->fresh()]);
    }

    private function validatedService(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'category' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:80'],
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            // Régi URL-eket adatkompatibilitás miatt továbbra is elfogadunk, de az admin UI már fájlfeltöltést használ.
            'image_url' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => [$required, 'integer', 'min:5', 'max:1440'],
            'buffer_minutes' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:240'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:999999900'],
            'price_mode' => [$partial ? 'sometimes' : 'nullable', Rule::in(['fixed', 'consultation', 'hidden'])],
            'active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
            'sort_order' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    private function authorizeService(Request $request, Service $service): void
    {
        $this->authorizeBusinessId($request, (int) $service->business_id);
    }

}
