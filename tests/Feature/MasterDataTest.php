<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected User $userB;
    protected Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();

        // Business A setup
        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);

        // Business B setup
        $this->userB = User::factory()->create();
        $this->businessB = Business::create(['name' => 'Usaha B']);
        BusinessUser::create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->userB->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Customer CRUD
     */
    public function test_customer_crud_operations(): void
    {
        // 1. Create Customer
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/customers', [
                'name' => 'Toko Sembako Jaya',
                'phone' => '0812345678',
                'address' => 'Jl. Merdeka No. 10',
            ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['customer' => ['name' => 'Toko Sembako Jaya']],
            ]);

        $customerId = $createRes->json('data.customer.id');

        // 2. List Customers
        $listRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/customers?search=Sembako');

        $listRes->assertStatus(200)
            ->assertJsonPath('data.total', 1);

        // 3. Update Customer
        $updateRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->putJson("/api/v1/customers/{$customerId}", [
                'name' => 'Toko Sembako Jaya Utama',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.customer.name', 'Toko Sembako Jaya Utama');

        // 4. Delete Customer
        $deleteRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->deleteJson("/api/v1/customers/{$customerId}");

        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    /**
     * Test Product CRUD & Initial Inventory Movement
     */
    public function test_product_crud_and_initial_inventory_movement(): void
    {
        // 1. Create Category
        $catRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/categories', [
                'name' => 'Minuman Kemasan',
            ]);
        $catId = $catRes->json('data.category.id');

        // 2. Create Product with initial stock = 50
        $prodRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/products', [
                'category_id' => $catId,
                'name' => 'Kopi Susu Gula Aren 250ml',
                'sku' => 'KSGA-250',
                'selling_price' => 18000,
                'cost_price' => 10000,
                'stock' => 50,
                'min_stock' => 10,
            ]);

        $prodRes->assertStatus(201)
            ->assertJsonPath('data.product.name', 'Kopi Susu Gula Aren 250ml')
            ->assertJsonPath('data.product.is_low_stock', false);

        $productId = $prodRes->json('data.product.id');

        // Verify initial inventory movement was automatically created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId,
            'quantity_change' => 50,
            'type' => 'IN',
        ]);

        // 3. Perform manual stock adjustment (+20 items)
        $adjRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/products/{$productId}/stock", [
                'quantity_change' => 20,
                'type' => 'IN',
                'notes' => 'Restock mingguan',
            ]);

        $adjRes->assertStatus(200)
            ->assertJsonPath('data.product.stock', 70);

        // Verify second movement was tracked
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId,
            'quantity_change' => 20,
            'notes' => 'Restock mingguan',
        ]);
    }

    /**
     * Test Tenant Isolation for Customers & Products
     */
    public function test_tenant_isolation_prevents_accessing_other_business_data(): void
    {
        // Create Customer & Product in Business B
        $customerB = Customer::create([
            'business_id' => $this->businessB->id,
            'name' => 'Pelanggan Rahasia B',
        ]);

        $productB = Product::create([
            'business_id' => $this->businessB->id,
            'name' => 'Produk Rahasia B',
            'selling_price' => 100000,
            'cost_price' => 50000,
            'stock' => 10,
        ]);

        // User A (Business A) attempts to list customers -> Customer B must NOT appear
        $listCustomersRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/customers');

        $listCustomersRes->assertStatus(200)
            ->assertJsonPath('data.total', 0);

        // User A attempts to view Customer B detail directly -> Expect 404 (due to global scope)
        $viewCustomerRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/customers/{$customerB->id}");

        $viewCustomerRes->assertStatus(404);

        // User A attempts to view Product B detail directly -> Expect 404
        $viewProductRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/products/{$productB->id}");

        $viewProductRes->assertStatus(404);
    }
}
