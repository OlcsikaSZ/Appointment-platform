<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_email_is_loaded_from_configuration_during_seeding(): void
    {
        config()->set('appointment.business_seed_email', 'notifications@example.test');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('businesses', [
            'slug' => 'default',
            'email' => 'notifications@example.test',
        ]);
    }

    public function test_seeding_stops_when_business_email_is_missing(): void
    {
        config()->set('appointment.business_seed_email', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BUSINESS_SEED_EMAIL');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeding_stops_when_business_email_is_invalid(): void
    {
        config()->set('appointment.business_seed_email', 'not-an-email');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BUSINESS_SEED_EMAIL');

        $this->seed(DatabaseSeeder::class);
    }
}
