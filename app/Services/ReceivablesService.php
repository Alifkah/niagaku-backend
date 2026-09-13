<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReceivablesService
{
    /**
     * Get summary metrics and customer breakdown for Receivables (Piutang)
     */
    public function getReceivablesData(string $businessId): array
    {
        $now = now();
        $sevenDays = now()->addDays(7);

        // 1. Total Receivables
        $totalReceivables = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->selectRaw('SUM(total - paid_amount) as total_unpaid')
            ->value('total_unpaid') ?? 0.00;

        // 2. Overdue Amount (deadline < now)
        $overdueAmount = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->where('deadline', '<', $now)
            ->selectRaw('SUM(total - paid_amount) as overdue')
            ->value('overdue') ?? 0.00;

        // 3. Due Soon Amount (deadline within next 7 days)
        $dueSoonAmount = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->whereBetween('deadline', [$now, $sevenDays])
            ->selectRaw('SUM(total - paid_amount) as due_soon')
            ->value('due_soon') ?? 0.00;

        // 4. Customer Breakdown
        $customerBreakdown = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->whereNotNull('customer_id')
            ->select(
                'customer_id',
                DB::raw('COUNT(id) as unpaid_orders_count'),
                DB::raw('SUM(total - paid_amount) as outstanding_balance')
            )
            ->groupBy('customer_id')
            ->with('customer')
            ->orderBy('outstanding_balance', 'desc')
            ->get();

        // 5. Unpaid Orders List
        $unpaidOrders = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->with(['customer', 'items'])
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC')
            ->paginate(15);

        return [
            'total_receivables' => $totalReceivables,
            'overdue_amount' => $overdueAmount,
            'due_soon_amount' => $dueSoonAmount,
            'customer_breakdown' => $customerBreakdown,
            'unpaid_orders' => $unpaidOrders,
        ];
    }
}
