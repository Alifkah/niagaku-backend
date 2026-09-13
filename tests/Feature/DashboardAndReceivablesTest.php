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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndReceivablesTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha Analytics A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Financial Formulas on Dashboard Endpoint
     * Sales: 20.000.000, Cost: 8.000.000, Expenses: 4.000.000
     * Expected: Gross Profit = 12.000.000, Net Profit = 8.000.000
     */
    public function test_dashboard_metrics_and_financial_formulas(): void
    {
        $customer = Customer::create([
            'business_id' => $this->businessA->id,
            'name' => 'PT Maju Terus',
        ]);

        $product = Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Produk A',
            'selling_price' => 20000000,
            'cost_price' => 8000000, // HPP = 8,000,000
            'stock' => 50,
        ]);

        // Create Order: Sales = 20,000,000
        $order = Order::create([
            'business_id' => $this->businessA->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-FIN-001',
            'subtotal' => 20000000,
            'discount' => 0,
            'total' => 20000000,
            'paid_amount' => 5000000, // DP = 5,000,000 (Receivables = 15,000,000)
            'payment_status' => 'PARTIAL',
            'status' => 'PROCESSING',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => 1,
            'unit_price' => 20000000,
            'cost_price_snapshot' => 8000000,
            'subtotal' => 20000000,
        ]);

        // Confirmed Payment = 5,000,000
        Payment::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'amount' => 5000000,
            'method' => 'BANK_TRANSFER',
            'status' => 'CONFIRMED',
        ]);

        // Operating Expense = 4,000,000
        Expense::create([
            'business_id' => $this->businessA->id,
            'description' => 'Sewa Gudang & Gaji',
            'category' => 'RENT',
            'amount' => 4000000,
        ]);

        // Call Dashboard API
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/dashboard');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revenue', 5000000)
            ->assertJsonPath('data.total_sales', 20000000)
            ->assertJsonPath('data.product_cost', 8000000)
            ->assertJsonPath('data.gross_profit', 12000000) // 20M - 8M = 12M
            ->assertJsonPath('data.operating_expenses', 4000000)
            ->assertJsonPath('data.net_profit', 8000000) // 12M - 4M = 8M
            ->assertJsonPath('data.total_receivables', 15000000) // 20M - 5M = 15M
            ->assertJsonPath('data.active_orders_count', 1);
    }

    /**
     * Test Receivables Breakdown & Overdue calculation
     */
    public function test_receivables_aging_and_customer_breakdown(): void
    {
        $customer = Customer::create([
            'business_id' => $this->businessA->id,
            'name' => 'Toko Kelontong Berkah',
        ]);

        // Overdue Order
        Order::create([
            'business_id' => $this->businessA->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-REC-001',
            'deadline' => now()->subDays(3), // Overdue 3 days ago
            'subtotal' => 3000000,
            'discount' => 0,
            'total' => 3000000,
            'paid_amount' => 1000000,
            'payment_status' => 'PARTIAL',
            'status' => 'PROCESSING',
        ]);

        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/receivables');

        $res->assertStatus(200)
            ->assertJsonPath('data.total_receivables', 2000000)
            ->assertJsonPath('data.overdue_amount', 2000000);
    }
}
