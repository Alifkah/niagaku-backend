<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected User $userB;
    protected Business $businessB;

    protected Order $orderA;

    protected function setUp(): void
    {
        parent::setUp();

        // Business A setup
        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha Finance A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);

        $productA = Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Jasa Desain Logo',
            'selling_price' => 1000000,
            'cost_price' => 200000,
            'stock' => 100,
        ]);

        $this->orderA = Order::create([
            'business_id' => $this->businessA->id,
            'order_number' => 'ORD-FIN-001',
            'subtotal' => 1000000,
            'discount' => 0,
            'total' => 1000000,
            'paid_amount' => 0,
            'payment_status' => 'UNPAID',
            'status' => 'PENDING',
        ]);

        // Business B setup
        $this->userB = User::factory()->create();
        $this->businessB = Business::create(['name' => 'Usaha Finance B']);
        BusinessUser::create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->userB->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Partial and Full Payments & Audit Log
     */
    public function test_partial_and_full_payments_update_order_status_and_create_audit_log(): void
    {
        // 1. Record Partial Payment Rp400.000
        $pay1 = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$this->orderA->id}/payments", [
                'amount' => 400000,
                'method' => 'BANK_TRANSFER',
                'notes' => 'DP 40%',
            ]);

        $pay1->assertStatus(201)
            ->assertJsonPath('data.payment.amount', '400000.00');

        $this->assertEquals(400000, $this->orderA->fresh()->paid_amount);
        $this->assertEquals('PARTIAL', $this->orderA->fresh()->payment_status);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $this->businessA->id,
            'action' => 'CREATE_PAYMENT',
        ]);

        // 2. Record Remaining Payment Rp600.000
        $pay2 = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$this->orderA->id}/payments", [
                'amount' => 600000,
                'method' => 'CASH',
                'notes' => 'Pelunasan',
            ]);

        $pay2->assertStatus(201);

        $this->assertEquals(1000000, $this->orderA->fresh()->paid_amount);
        $this->assertEquals('PAID', $this->orderA->fresh()->payment_status);
    }

    /**
     * Test Overpayment Rejection Rule
     */
    public function test_overpayment_is_strictly_rejected(): void
    {
        // Outstanding is 1.000.000. Attempting to pay 1.200.000
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/orders/{$this->orderA->id}/payments", [
                'amount' => 1200000,
                'method' => 'CASH',
            ]);

        $res->assertStatus(422);
    }

    /**
     * Test Expense CRUD & Audit Logging
     */
    public function test_expense_crud_and_audit_logging(): void
    {
        // 1. Create Expense
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/expenses', [
                'description' => 'Pembelian Kertas & Tinta',
                'category' => 'RAW_MATERIAL',
                'amount' => 250000,
                'payment_method' => 'CASH',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.expense.description', 'Pembelian Kertas & Tinta');

        $expenseId = $createRes->json('data.expense.id');

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $this->businessA->id,
            'action' => 'CREATE_EXPENSE',
        ]);

        // 2. Update Expense
        $updateRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->putJson("/api/v1/expenses/{$expenseId}", [
                'description' => 'Pembelian Kertas & Tinta Premium',
                'category' => 'RAW_MATERIAL',
                'amount' => 300000,
            ]);

        $updateRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_EXPENSE',
        ]);

        // 3. Delete Expense
        $deleteRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->deleteJson("/api/v1/expenses/{$expenseId}");

        $deleteRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE_EXPENSE',
        ]);
    }

    /**
     * Test Tenant Isolation for Expenses
     */
    public function test_tenant_isolation_for_expenses(): void
    {
        $expenseB = Expense::create([
            'business_id' => $this->businessB->id,
            'description' => 'Beli Alat Tulis B',
            'category' => 'OPERATIONAL',
            'amount' => 50000,
        ]);

        // User A attempts to view Expense B -> Expect 404
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson("/api/v1/expenses/{$expenseB->id}");

        $res->assertStatus(404);
    }
}
