<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerA;
    protected User $adminA;
    protected User $ownerB;

    protected Business $businessA;
    protected Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);

        // User A (Owner of Business A)
        $this->ownerA = User::factory()->create(['name' => 'Owner Business A']);
        $this->businessA = Business::create(['name' => 'PT Niaga Bersama A', 'city' => 'Jakarta']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->ownerA->id,
            'role' => 'OWNER',
        ]);

        // Admin A (Staff of Business A)
        $this->adminA = User::factory()->create(['name' => 'Admin Staff A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'role' => 'ADMIN',
        ]);

        // User B (Owner of Business B)
        $this->ownerB = User::factory()->create(['name' => 'Owner Business B']);
        $this->businessB = Business::create(['name' => 'CV Sukses Mandiri B', 'city' => 'Surabaya']);
        BusinessUser::create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->ownerB->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * 1. Full E2E Journey: Register -> Customer -> Product -> Order -> Payments -> Expense -> Analytics -> AI
     */
    public function test_full_production_e2e_journey(): void
    {
        // Step A: Create Customer
        $custRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/customers', [
                'name' => 'Budi Santoso',
                'phone' => '08123456789',
                'email' => 'budi@gmail.com',
                'address' => 'Jl. Sudirman No. 12',
            ]);
        $custRes->assertStatus(201);
        $customerId = $custRes->json('data.customer.id');

        // Step B: Create Product
        $prodRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/products', [
                'name' => 'Kopi Robusta Premium 500g',
                'selling_price' => 100000,
                'cost_price' => 40000,
                'stock' => 50,
                'min_stock' => 5,
            ]);
        $prodRes->assertStatus(201);
        $productId = $prodRes->json('data.product.id');

        // Step C: Create Order (Total = 5 x 100,000 = 500,000)
        $orderRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customerId,
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => 5,
                        'unit_price' => 100000,
                    ],
                ],
                'discount' => 0,
                'deadline' => now()->addDays(5)->toDateString(),
            ]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('data.order.id');

        // Step D1: Record DP Payment (200,000)
        $dpRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'amount' => 200000,
                'method' => 'CASH',
                'notes' => 'Uang muka (DP)',
            ]);
        $dpRes->assertStatus(201);

        // Step D2: Record Final Payment (Sisa Tagihan = 300,000 -> PAID)
        $payRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'amount' => 300000,
                'method' => 'BANK_TRANSFER',
                'notes' => 'Pelunasan sisa order',
            ]);
        $payRes->assertStatus(201);

        // Step E: Overpayment Rejection Test (Attempting 1 Rp extra payment on fully paid order must fail 422)
        $overpayRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'amount' => 1,
                'method' => 'CASH',
            ]);
        $overpayRes->assertStatus(422);

        // Step F: Record Expense (Operational = 50,000)
        $expRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/expenses', [
                'description' => 'Pembelian Kemasan Dus',
                'category' => 'OPERATIONAL',
                'amount' => 50000,
                'payment_method' => 'CASH',
            ]);
        $expRes->assertStatus(201);

        // Step G: Dashboard Analytics Audit (Sales = 500k, HPP = 200k, Gross = 300k, Expense = 50k, Net = 250k)
        $dashRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/dashboard');

        $dashRes->assertStatus(200)
            ->assertJsonPath('data.total_sales', 500000)
            ->assertJsonPath('data.product_cost', 200000)
            ->assertJsonPath('data.gross_profit', 300000)
            ->assertJsonPath('data.operating_expenses', 50000)
            ->assertJsonPath('data.net_profit', 250000)
            ->assertJsonPath('data.total_receivables', 0); // Fully paid!

        // Step H: Reports Audit
        $repRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/reports?preset=today');

        $repRes->assertStatus(200)
            ->assertJsonPath('data.profit_report.net_profit', 250000);

        // Step I: NiagaKu AI Query Audit
        $aiConvRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/ai/conversations', ['title' => 'Audit E2E']);
        $convId = $aiConvRes->json('data.conversation.id');

        $aiMsgRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/ai/conversations/{$convId}/messages", [
                'message' => 'Berapa net profit saya hari ini?',
            ]);
        $aiMsgRes->assertStatus(200);
        $this->assertStringContainsString('Ringkasan Performa Laba Rugi', $aiMsgRes->json('data.assistant_message.content'));
    }

    /**
     * 2. Comprehensive Tenant Isolation Matrix Audit across all modules
     */
    public function test_comprehensive_tenant_isolation_matrix(): void
    {
        // Setup Business B Data
        $custB = Customer::create(['business_id' => $this->businessB->id, 'name' => 'Customer B']);
        $prodB = Product::create(['business_id' => $this->businessB->id, 'name' => 'Product B', 'selling_price' => 50000, 'cost_price' => 20000, 'stock' => 10]);
        $orderB = Order::create(['business_id' => $this->businessB->id, 'customer_id' => $custB->id, 'order_number' => 'ORD-B-001', 'subtotal' => 50000, 'discount' => 0, 'total' => 50000, 'paid_amount' => 0, 'payment_status' => 'UNPAID', 'status' => 'PENDING']);
        $expB = Expense::create(['business_id' => $this->businessB->id, 'description' => 'Expense B', 'category' => 'RENT', 'amount' => 100000]);

        // Attempting to access Business B items while authenticated in Business A
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/customers/{$custB->id}")
            ->assertStatus(404);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/products/{$prodB->id}")
            ->assertStatus(404);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/orders/{$orderB->id}")
            ->assertStatus(404);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/expenses/{$expB->id}")
            ->assertStatus(404);
    }

    /**
     * 3. Role Authorization Matrix Audit (OWNER vs ADMIN)
     */
    public function test_role_authorization_matrix(): void
    {
        // Admin staff should be able to create operational products & orders
        $this->actingAs($this->adminA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/products', [
                'name' => 'Produk Admin',
                'selling_price' => 20000,
                'cost_price' => 10000,
                'stock' => 15,
            ])
            ->assertStatus(201);

        // Financial access check endpoint for ADMIN must return 403 Forbidden
        $this->actingAs($this->adminA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/business/financial-access-check')
            ->assertStatus(403);

        // Financial access check endpoint for OWNER must succeed 200
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/business/financial-access-check')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
