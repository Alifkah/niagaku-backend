<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected User $userB;
    protected Business $businessB;

    protected Customer $customerA;
    protected Product $productA1;
    protected Product $productA2;

    protected function setUp(): void
    {
        parent::setUp();

        // Business A setup
        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha Order A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);

        $this->customerA = Customer::create([
            'business_id' => $this->businessA->id,
            'name' => 'Budi Santoso',
            'phone' => '081299998888',
        ]);

        $this->productA1 = Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Kaos Polos Cotton 30s',
            'selling_price' => 50000,
            'cost_price' => 30000, // HPP = 30k
            'stock' => 100,
        ]);

        $this->productA2 = Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Sablon Custom A3',
            'selling_price' => 25000,
            'cost_price' => 10000, // HPP = 10k
            'stock' => 50,
        ]);

        // Business B setup
        $this->userB = User::factory()->create();
        $this->businessB = Business::create(['name' => 'Usaha Order B']);
        BusinessUser::create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->userB->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Order Creation & Cost Price Snapshotting
     */
    public function test_order_creation_with_item_snapshots_and_stock_deduction(): void
    {
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/orders', [
                'customer_id' => $this->customerA->id,
                'discount' => 5000,
                'paid_amount' => 20000, // DP 20k
                'items' => [
                    [
                        'product_id' => $this->productA1->id,
                        'quantity' => 2, // 2 x 50,000 = 100,000
                    ],
                    [
                        'product_id' => $this->productA2->id,
                        'quantity' => 1, // 1 x 25,000 = 25,000
                    ],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.subtotal', '125000.00')
            ->assertJsonPath('data.order.discount', '5000.00')
            ->assertJsonPath('data.order.total', '120000.00')
            ->assertJsonPath('data.order.paid_amount', '20000.00')
            ->assertJsonPath('data.order.payment_status', 'PARTIAL');

        $orderId = $res->json('data.order.id');

        // Check Order Items HPP Snapshots in database
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $this->productA1->id,
            'quantity' => 2,
            'unit_price' => 50000.00,
            'cost_price_snapshot' => 30000.00, // HPP snapshot
        ]);

        // Check inventory deduction
        $this->assertEquals(98, $this->productA1->fresh()->stock); // 100 - 2 = 98
        $this->assertEquals(49, $this->productA2->fresh()->stock); // 50 - 1 = 49

        // Change current product cost price later
        $this->productA1->update(['cost_price' => 45000]); // HPP raised to 45k

        // Historical order item snapshot MUST remain 30,000.00!
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $this->productA1->id,
            'cost_price_snapshot' => 30000.00,
        ]);
    }

    /**
     * Test Cross-Tenant Customer or Product usage prevention
     */
    public function test_cross_tenant_validation_prevents_using_customer_or_product_from_another_business(): void
    {
        // Customer in Business B
        $customerB = Customer::create([
            'business_id' => $this->businessB->id,
            'name' => 'Pelanggan B',
        ]);

        // Attempting to create Order in Business A using Customer B -> Expect 422
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customerB->id,
                'items' => [
                    ['product_id' => $this->productA1->id, 'quantity' => 1],
                ],
            ]);

        $res->assertStatus(422);
    }

    /**
     * Test Order Cancellation restores product inventory
     */
    public function test_order_cancellation_restores_inventory(): void
    {
        // 1. Create order
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $this->productA1->id, 'quantity' => 5],
                ],
            ]);

        $orderId = $createRes->json('data.order.id');
        $this->assertEquals(95, $this->productA1->fresh()->stock); // 100 - 5 = 95

        // 2. Cancel order
        $cancelRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$orderId}/cancel");

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.order.status', 'CANCELLED');

        // Verify stock was restored to 100
        $this->assertEquals(100, $this->productA1->fresh()->stock);
    }
}
