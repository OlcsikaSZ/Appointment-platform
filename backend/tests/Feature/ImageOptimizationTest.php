<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\ImageOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_service_upload_creates_webp_main_image_and_thumbnail(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('A PHP GD WebP támogatás nem elérhető.');
        }

        $business = $this->createBusiness();
        $service = $this->createService($business);
        Sanctum::actingAs($this->createAdmin($business), ['admin']);

        $response = $this->post(
            "/api/v1/admin/services/{$service->id}/image",
            ['image' => UploadedFile::fake()->image('service.jpg', 2200, 1600)->size(1400)],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('image.format', 'webp');

        $fresh = Service::findOrFail($service->id);
        $this->assertStringEndsWith('.webp', (string) $fresh->image_url);
        $this->assertStringEndsWith('-thumb.webp', (string) $fresh->image_thumbnail_url);
        $this->assertFileExists(storage_path('app/public/services/'.basename((string) $fresh->image_url)));
        $this->assertFileExists(storage_path('app/public/services/'.basename((string) $fresh->image_thumbnail_url)));

        app(ImageOptimizationService::class)->delete(
            [$fresh->image_url, $fresh->image_thumbnail_url],
            'services',
        );
    }
}
