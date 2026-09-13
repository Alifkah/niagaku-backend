<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get high-performance aggregated dashboard metrics
     */
    public function getDashboardMetrics(string $businessId): array
    {
        // 1. Confirmed Revenue
        $revenue = (float) Payment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'CONFIRMED')
            ->sum('amount');

        // 2. Total Sales & HPP Product Cost (non-cancelled orders)
        $totalSales = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->sum('total');

        $productCost = (float) OrderItem::whereHas('order', function ($q) use ($businessId) {
            $q->withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('status', '!=', 'CANCELLED');
        })->selectRaw('SUM(quantity * cost_price_snapshot) as total_cost')->value('total_cost') ?? 0.00;

        // 3. Profit Calculations
        $grossProfit = max(0.00, $totalSales - $productCost);

        $operatingExpenses = (float) Expense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->sum('amount');

        $netProfit = $grossProfit - $operatingExpenses;

        $grossMargin = $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 1) : 0;
        $netMargin = $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 1) : 0;

        // 4. Receivables (Unpaid & Partial Order Balances)
        $totalReceivables = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->selectRaw('SUM(total - paid_amount) as total_unpaid')
            ->value('total_unpaid') ?? 0.00;

        // 5. Active Orders Count & Urgent Orders
        $activeOrdersCount = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'READY'])
            ->count();

        $urgentOrders = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'READY'])
            ->with('customer')
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC')
            ->limit(5)
            ->get();

        return [
            'revenue' => $revenue,
            'total_sales' => $totalSales,
            'product_cost' => $productCost,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
            'gross_margin_percent' => $grossMargin,
            'net_margin_percent' => $netMargin,
            'total_receivables' => $totalReceivables,
            'active_orders_count' => $activeOrdersCount,
            'urgent_orders' => $urgentOrders,
        ];
    }
}
