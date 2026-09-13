<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API Health Check Endpoint
     */
    public function test_api_health_check_returns_operational_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'app' => 'NiagaKu API',
                'status' => 'operational',
            ]);
    }

    /**
     * Test Foundation Schema Tables Exist
     */
    public function test_foundation_tables_exist_in_database(): void
    {
        $this->assertTrue(Schema::hasTable('users'), 'Users table missing');
        $this->assertTrue(Schema::hasTable('businesses'), 'Businesses table missing');
        $this->assertTrue(Schema::hasTable('business_users'), 'BusinessUsers table missing');
        $this->assertTrue(Schema::hasTable('personal_access_tokens'), 'Sanctum tokens table missing');
    }
}
