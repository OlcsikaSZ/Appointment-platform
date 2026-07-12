<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminServiceController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->services()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
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

        $this->deleteStoredImage($service->image_url);
        $service->delete();

        return response()->json(['message' => 'Szolgáltatás törölve.']);
    }

    public function reorder(Request $request, Business $business): JsonResponse
    {
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

        return $this->index($business);
    }

    /**
     * Valódi képfeltöltés a szolgáltatáshoz. A fájl a backend publikus storage könyvtárába kerül,
     * a frontend pedig a meglévő /uploads rewrite-on keresztül éri el.
     */
    public function uploadImage(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $directory = storage_path('app/public/services');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            abort(500, 'Nem sikerült létrehozni a szolgáltatásképek mappáját.');
        }

        $this->deleteStoredImage($service->image_url);

        $file = $request->file('image');
        $extension = strtolower($file->extension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $file->move($directory, $filename);

        $service->update([
            'image_url' => './uploads/services/'.$filename,
        ]);

        return response()->json(['data' => $service->fresh()]);
    }

    public function deleteImage(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);
        $this->deleteStoredImage($service->image_url);
        $service->update(['image_url' => null]);

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
            'active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
            'sort_order' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_unless((int) $request->user()?->business_id === (int) $service->business_id, 403);
    }

    private function deleteStoredImage(?string $imageUrl): void
    {
        if (! $imageUrl || ! str_starts_with(ltrim($imageUrl, './'), 'uploads/services/')) {
            return;
        }

        $filename = preg_replace('#^uploads/services/#', '', ltrim($imageUrl, './'));
        $paths = [
            storage_path('app/public/services/'.$filename),
            dirname(base_path()).DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'services'.DIRECTORY_SEPARATOR.$filename,
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
