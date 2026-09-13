<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndTenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test User Registration
     */
    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'phone' => '081234567890',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    /**
     * Test User Login
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'siti@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'siti@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    /**
     * Test Business Onboarding (Creation by User)
     */
    public function test_user_can_create_business_during_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/business/onboarding', [
            'name' => 'Toko Kopi Niaga',
            'type' => 'Kuliner',
            'city' => 'Balikpapan',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business' => ['name' => 'Toko Kopi Niaga', 'type' => 'Kuliner'],
                    'role' => 'OWNER',
                ],
            ]);

        $this->assertDatabaseHas('businesses', ['name' => 'Toko Kopi Niaga']);
        $this->assertDatabaseHas('business_users', ['user_id' => $user->id, 'role' => 'OWNER']);
    }

    /**
     * Test EnsureActiveBusiness Middleware blocks users without a business
     */
    public function test_user_without_business_is_prompted_for_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/business');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'require_onboarding' => true,
            ]);
    }

    /**
     * Test Role Authorization (OWNER vs ADMIN)
     */
    public function test_owner_can_access_financials_while_admin_is_rejected(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $business = Business::create(['name' => 'Usaha Bersama']);

        BusinessUser::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => 'OWNER']);
        BusinessUser::create(['business_id' => $business->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);

        // Owner access -> Allowed
        $ownerResponse = $this->actingAs($owner)
            ->withHeader('X-Business-ID', $business->id)
            ->getJson('/api/v1/business/financial-access-check');

        $ownerResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Admin access -> Forbidden
        $adminResponse = $this->actingAs($admin)
            ->withHeader('X-Business-ID', $business->id)
            ->getJson('/api/v1/business/financial-access-check');

        $adminResponse->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * CRITICAL TEST CASE: Cross-Tenant Security Isolation Test
     */
    public function test_cross_tenant_access_attempt_is_strictly_rejected(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $businessA = Business::create(['name' => 'Warung A']);
        $businessB = Business::create(['name' => 'Warung B']);

        BusinessUser::create(['business_id' => $businessA->id, 'user_id' => $userA->id, 'role' => 'OWNER']);
        BusinessUser::create(['business_id' => $businessB->id, 'user_id' => $userB->id, 'role' => 'OWNER']);

        // User A attempts to request Business B data using X-Business-ID header
        $response = $this->actingAs($userA)
            ->withHeader('X-Business-ID', $businessB->id)
            ->getJson('/api/v1/business');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke usaha ini.',
            ]);
    }
}
