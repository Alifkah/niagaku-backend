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

class ReportAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha Laporan A']);
        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test Report Generation & Date Preset Filtering
     */
    public function test_reports_date_filtering_and_analytics_calculations(): void
    {
        $customer = Customer::create([
            'business_id' => $this->businessA->id,
            'name' => 'Pelanggan Setia',
        ]);

        $product = Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Produk Best Seller',
            'selling_price' => 100000,
            'cost_price' => 40000,
            'stock' => 20,
        ]);

        // Order today
        $order = Order::create([
            'business_id' => $this->businessA->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-REP-001',
            'order_date' => now(),
            'subtotal' => 500000,
            'discount' => 0,
            'total' => 500000,
            'paid_amount' => 500000,
            'payment_status' => 'PAID',
            'status' => 'COMPLETED',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => 5,
            'unit_price' => 100000,
            'cost_price_snapshot' => 40000,
            'subtotal' => 500000,
        ]);

        Payment::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'amount' => 500000,
            'date' => now(),
            'method' => 'CASH',
            'status' => 'CONFIRMED',
        ]);

        Expense::create([
            'business_id' => $this->businessA->id,
            'description' => 'Biaya Kantong Plastik',
            'category' => 'OPERATIONAL',
            'amount' => 50000,
            'date' => now(),
        ]);

        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/reports?preset=today');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sales_report.revenue', 500000)
            ->assertJsonPath('data.sales_report.order_count', 1)
            ->assertJsonPath('data.sales_report.average_order_value', 500000)
            ->assertJsonPath('data.profit_report.product_cost', 200000) // 5 x 40,000 = 200,000
            ->assertJsonPath('data.profit_report.gross_profit', 300000) // 500k - 200k = 300k
            ->assertJsonPath('data.profit_report.expenses', 50000)
            ->assertJsonPath('data.profit_report.net_profit', 250000); // 300k - 50k = 250k
    }

    /**
     * Test CSV Export Stream
     */
    public function test_csv_export_stream(): void
    {
        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->get('/api/v1/reports/export?type=profit&preset=this_month');

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test Notifications & Low Stock Alerts
     */
    public function test_notifications_list_and_unread_count(): void
    {
        // Low Stock Product
        Product::create([
            'business_id' => $this->businessA->id,
            'name' => 'Barang Habis',
            'selling_price' => 10000,
            'cost_price' => 5000,
            'stock' => 1,
            'min_stock' => 5,
        ]);

        $res = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->getJson('/api/v1/notifications');

        $res->assertStatus(200)
            ->assertJsonPath('success', true);
        
        $this->assertGreaterThanOrEqual(1, count($res->json('data.notifications')));
    }
}
