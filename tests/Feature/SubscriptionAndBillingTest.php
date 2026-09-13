<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAndBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);

        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha Subscription A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Default FREE plan assignment and usage response
     */
    public function test_default_free_plan_assigned_and_usage_reported(): void
    {
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/subscription/current');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.code', 'FREE')
            ->assertJsonPath('data.usage.products.max', 20)
            ->assertJsonPath('data.usage.orders_this_month.max', 50);
    }

    /**
     * Test product creation limit enforcement (FREE plan max 20 products)
     */
    public function test_limit_enforcement_on_products(): void
    {
        // Create 20 products (Limit max for FREE plan)
        for ($i = 1; $i <= 20; $i++) {
            Product::create([
                'business_id' => $this->businessA->id,
                'name' => "Produk Dummy #{$i}",
                'selling_price' => 10000,
                'cost_price' => 5000,
                'stock' => 10,
            ]);
        }

        // 21st product creation attempt must fail with 403 Forbidden
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/products', [
                'name' => 'Produk Terlarang 21',
                'selling_price' => 15000,
                'cost_price' => 7000,
                'stock' => 5,
            ]);

        $res->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'LIMIT_REACHED');
    }

    /**
     * Test Webhook Payment upgrades subscription and unlocks feature limits
     */
    public function test_webhook_payment_upgrades_subscription_and_unlocks_limits(): void
    {
        // 1. Create 20 products
        for ($i = 1; $i <= 20; $i++) {
            Product::create([
                'business_id' => $this->businessA->id,
                'name' => "Produk Dummy #{$i}",
                'selling_price' => 10000,
                'cost_price' => 5000,
                'stock' => 10,
            ]);
        }

        // 2. Trigger Payment Gateway Webhook for STARTER upgrade
        $webhookRes = $this->postJson('/api/v1/subscription/webhook', [
            'business_id' => $this->businessA->id,
            'plan_code' => 'STARTER',
            'order_id' => 'MIDTRANS-INV-12345',
            'signature_key' => 'valid_mock_signature',
        ]);

        $webhookRes->assertStatus(200)->assertJsonPath('success', true);

        // 3. Verify Plan is now STARTER
        $currentRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/subscription/current');

        $currentRes->assertStatus(200)
            ->assertJsonPath('data.plan.code', 'STARTER')
            ->assertJsonPath('data.usage.products.max', 500);

        // 4. Product 21 creation attempt must NOW SUCCEED!
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/products', [
                'name' => 'Produk Ke-21 Setelah Upgrade',
                'selling_price' => 15000,
                'cost_price' => 7000,
                'stock' => 5,
            ]);

        $createRes->assertStatus(201)->assertJsonPath('success', true);
    }
}
