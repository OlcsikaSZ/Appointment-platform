<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_bootstrap_creates_business_without_demo_content(): void
    {
        $this->artisan('app:bootstrap-client', [
            '--slug' => 'teszt-ugyfel',
            '--name' => 'Teszt Ügyfél',
            '--email' => 'owner@example.test',
            '--timezone' => 'Europe/Budapest',
        ])->assertSuccessful();

        $business = Business::query()->where('slug', 'teszt-ugyfel')->firstOrFail();
        $this->assertSame('Teszt Ügyfél', $business->name);
        $this->assertSame('owner@example.test', $business->email);
        $this->assertCount(5, $business->workingHours()->get());
        $this->assertSame(0, $business->services()->count());
        $this->assertSame(0, $business->reviews()->count());
    }

    public function test_client_bootstrap_refuses_to_overwrite_existing_business(): void
    {
        Business::query()->create([
            'name' => 'Meglévő',
            'slug' => 'default',
            'email' => 'existing@example.test',
        ]);

        $this->artisan('app:bootstrap-client', [
            '--slug' => 'default',
            '--name' => 'Másik',
            '--email' => 'other@example.test',
        ])->assertFailed();

        $this->assertDatabaseMissing('businesses', ['email' => 'other@example.test']);
    }

    public function test_backup_command_is_safe_by_default_when_disabled(): void
    {
        config()->set('backup.enabled', false);

        $this->artisan('app:backup')
            ->expectsOutputToContain('BACKUP_ENABLED')
            ->assertFailed();
    }
}
