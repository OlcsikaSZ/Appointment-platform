<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_upgrade_removes_required_legacy_author_name_column(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('author_name', 120);
        });

        $this->assertTrue(Schema::hasColumn('reviews', 'author_name'));

        $migration = require database_path(
            'migrations/2026_08_12_000004_remove_legacy_author_name_from_reviews_table.php'
        );
        $migration->up();

        $this->assertFalse(Schema::hasColumn('reviews', 'author_name'));

        $this->createBusiness(['slug' => 'legacy-review-business']);

        $this->postJson('/api/v1/businesses/legacy-review-business/reviews', [
            'author' => 'Régi Adatbázis',
            'email' => 'legacy@example.test',
            'rating' => 5,
            'text' => 'A frissítés után is sikeresen beküldhető a vélemény.',
            'legal_accepted' => true,
            'website' => '',
        ])->assertCreated();
    }

    public function test_customer_review_is_validated_queued_for_moderation_and_only_shown_after_approval(): void
    {
        $business = $this->createBusiness(['slug' => 'review-business']);
        $admin = $this->createAdmin($business);

        $this->postJson('/api/v1/businesses/review-business/reviews', [
            'author' => 'Kovács Anna',
            'email' => 'anna@example.test',
            'rating' => 5,
            'text' => 'Nagyon kedves és pontos szolgáltatást kaptam.',
            'legal_accepted' => true,
            'website' => '',
        ])->assertCreated()
            ->assertJsonPath('data.moderation_status', Review::STATUS_PENDING);

        $review = Review::query()->sole();
        $this->assertSame(Review::SOURCE_CUSTOMER, $review->source);
        $this->assertSame(Review::STATUS_PENDING, $review->moderation_status);
        $this->assertFalse($review->active);
        $this->assertSame('anna@example.test', $review->submitter_email);

        $this->getJson('/api/v1/businesses/review-business')
            ->assertOk()
            ->assertJsonCount(0, 'data.reviews');

        Sanctum::actingAs($admin, ['admin']);
        $this->patchJson("/api/v1/admin/reviews/{$review->id}", [
            'moderation_status' => Review::STATUS_APPROVED,
            'active' => true,
        ])->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.moderation_status', Review::STATUS_APPROVED);

        $this->getJson('/api/v1/businesses/review-business')
            ->assertOk()
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonMissingPath('data.reviews.0.submitter_email');
    }

    public function test_public_review_rejects_invalid_data(): void
    {
        $this->createBusiness(['slug' => 'invalid-review-business']);

        $this->postJson('/api/v1/businesses/invalid-review-business/reviews', [
            'author' => '1',
            'email' => 'nem-email',
            'rating' => 6,
            'text' => 'rövid',
            'legal_accepted' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['author', 'email', 'rating', 'text', 'legal_accepted']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_admin_cannot_moderate_another_business_review(): void
    {
        $ownBusiness = $this->createBusiness(['slug' => 'review-owner']);
        $otherBusiness = $this->createBusiness(['slug' => 'review-other']);
        $admin = $this->createAdmin($ownBusiness);
        $review = $otherBusiness->reviews()->create([
            'author' => 'Másik Vendég',
            'text' => 'Másik vállalkozás véleménye.',
            'rating' => 4,
            'source' => Review::SOURCE_CUSTOMER,
            'moderation_status' => Review::STATUS_PENDING,
            'active' => false,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/reviews/{$review->id}", [
            'moderation_status' => Review::STATUS_APPROVED,
            'active' => true,
        ])->assertForbidden();
    }
}
