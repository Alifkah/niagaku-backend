<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Helper to resolve start and end Carbon dates based on preset or custom parameters
     */
    public function resolveDateRange(?string $preset = 'this_month', ?string $startDate = null, ?string $endDate = null): array
    {
        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'custom':
                $start = $startDate ? Carbon::parse($startDate)->startOfDay() : $now->copy()->startOfMonth();
                $end = $endDate ? Carbon::parse($endDate)->endOfDay() : $now->copy()->endOfMonth();
                break;
            case 'this_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
        }

        return [$start, $end];
    }

    /**
     * Get Sales Report analytics
     */
    public function getSalesReport(string $businessId, Carbon $start, Carbon $end): array
    {
        $revenue = (float) Payment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'CONFIRMED')
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $totalSales = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('order_date', [$start, $end])
            ->sum('total');

        $orderCount = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('order_date', [$start, $end])
            ->count();

        $aov = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0.00;

        // Top 5 Customers
        $topCustomers = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('order_date', [$start, $end])
            ->whereNotNull('customer_id')
            ->select(
                'customer_id',
                DB::raw('COUNT(id) as total_orders'),
                DB::raw('SUM(total) as total_spend')
            )
            ->groupBy('customer_id')
            ->with('customer')
            ->orderBy('total_spend', 'desc')
            ->limit(5)
            ->get();

        // Top 5 Products
        $topProducts = OrderItem::whereHas('order', function ($q) use ($businessId, $start, $end) {
            $q->withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('status', '!=', 'CANCELLED')
                ->whereBetween('order_date', [$start, $end]);
        })
            ->select(
                'product_name_snapshot',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->groupBy('product_name_snapshot')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get();

        return [
            'revenue' => $revenue,
            'total_sales' => $totalSales,
            'order_count' => $orderCount,
            'average_order_value' => $aov,
            'top_customers' => $topCustomers,
            'top_products' => $topProducts,
        ];
    }

    /**
     * Get Profit Report analytics (Sales, HPP, Gross Profit, Expenses, Net Profit)
     */
    public function getProfitReport(string $businessId, Carbon $start, Carbon $end): array
    {
        $revenue = (float) Payment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'CONFIRMED')
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $totalSales = (float) Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('order_date', [$start, $end])
            ->sum('total');

        $productCost = (float) OrderItem::whereHas('order', function ($q) use ($businessId, $start, $end) {
            $q->withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('status', '!=', 'CANCELLED')
                ->whereBetween('order_date', [$start, $end]);
        })->selectRaw('SUM(quantity * cost_price_snapshot) as total_cost')->value('total_cost') ?? 0.00;

        $grossProfit = max(0.00, $totalSales - $productCost);

        $expenses = (float) Expense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $netProfit = $grossProfit - $expenses;

        $grossMargin = $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 1) : 0;
        $netMargin = $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 1) : 0;

        return [
            'revenue' => $revenue,
            'total_sales' => $totalSales,
            'product_cost' => $productCost,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'net_profit' => $netProfit,
            'gross_margin_percent' => $grossMargin,
            'net_margin_percent' => $netMargin,
        ];
    }

    /**
     * Get Expense Report analytics
     */
    public function getExpenseReport(string $businessId, Carbon $start, Carbon $end): array
    {
        $totalExpenses = (float) Expense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $categoryBreakdown = Expense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('date', [$start, $end])
            ->select(
                'category',
                DB::raw('COUNT(id) as expense_count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->map(function ($cat) use ($totalExpenses) {
                $cat->percentage = $totalExpenses > 0 ? round(($cat->total_amount / $totalExpenses) * 100, 1) : 0;
                return $cat;
            });

        $highestExpenses = Expense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('amount', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_expenses' => $totalExpenses,
            'category_breakdown' => $categoryBreakdown,
            'highest_expenses' => $highestExpenses,
        ];
    }

    /**
     * Generate CSV export content for reports
     */
    public function generateCsvExport(string $businessId, string $reportType, Carbon $start, Carbon $end): string
    {
        $output = fopen('php://temp', 'r+');

        if ($reportType === 'sales') {
            fputcsv($output, ['Laporan Penjualan NiagaKu']);
            fputcsv($output, ['Periode', $start->toDateString() . ' s/d ' . $end->toDateString()]);
            fputcsv($output, []);
            fputcsv($output, ['No. Pesanan', 'Tanggal', 'Pelanggan', 'Status', 'Total (Rp)', 'Dibayar (Rp)']);

            $orders = Order::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereBetween('order_date', [$start, $end])
                ->with('customer')
                ->get();

            foreach ($orders as $o) {
                fputcsv($output, [
                    $o->order_number,
                    $o->order_date ? $o->order_date->toDateString() : '',
                    $o->customer?->name ?? 'Umum',
                    $o->status,
                    $o->total,
                    $o->paid_amount,
                ]);
            }
        } elseif ($reportType === 'expenses') {
            fputcsv($output, ['Laporan Pengeluaran NiagaKu']);
            fputcsv($output, ['Periode', $start->toDateString() . ' s/d ' . $end->toDateString()]);
            fputcsv($output, []);
            fputcsv($output, ['Tanggal', 'Deskripsi', 'Kategori', 'Metode', 'Jumlah (Rp)']);

            $expenses = Expense::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereBetween('date', [$start, $end])
                ->get();

            foreach ($expenses as $e) {
                fputcsv($output, [
                    $e->date ? $e->date->toDateString() : '',
                    $e->description,
                    $e->category,
                    $e->payment_method,
                    $e->amount,
                ]);
            }
        } else {
            $profit = $this->getProfitReport($businessId, $start, $end);
            fputcsv($output, ['Laporan Laba Rugi NiagaKu']);
            fputcsv($output, ['Periode', $start->toDateString() . ' s/d ' . $end->toDateString()]);
            fputcsv($output, []);
            fputcsv($output, ['Metrik', 'Nilai (Rp)']);
            fputcsv($output, ['Total Omzet (Revenue)', $profit['revenue']]);
            fputcsv($output, ['Total Penjualan (Sales)', $profit['total_sales']]);
            fputcsv($output, ['Harga Pokok Penjualan (HPP)', $profit['product_cost']]);
            fputcsv($output, ['Laba Kotor (Gross Profit)', $profit['gross_profit']]);
            fputcsv($output, ['Biaya Operasional (Expenses)', $profit['expenses']]);
            fputcsv($output, ['Laba Bersih (Net Profit)', $profit['net_profit']]);
            fputcsv($output, ['Margin Laba Bersih (%)', $profit['net_margin_percent'] . '%']);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }
}
