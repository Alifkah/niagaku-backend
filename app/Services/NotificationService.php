<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Get system alerts & notifications list for active business
     */
    public function getActiveBusinessNotifications(string $businessId): array
    {
        $now = Carbon::now();
        $twoDaysLater = $now->copy()->addDays(2);
        $alerts = [];

        // 1. Low Stock Alerts
        $lowStockProducts = Product::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereColumn('stock', '<=', 'min_stock')
            ->limit(5)
            ->get();

        foreach ($lowStockProducts as $p) {
            $alerts[] = [
                'id' => "low_stock_{$p->id}",
                'type' => 'LOW_STOCK',
                'title' => 'Stok Produk Menipis',
                'message' => "Stok {$p->name} tersisa {$p->stock} (Batas min: {$p->min_stock})",
                'read' => false,
                'created_at' => $p->updated_at ? $p->updated_at->toIso8601String() : $now->toIso8601String(),
                'action_url' => '/products',
            ];
        }

        // 2. Overdue Orders Alerts
        $overdueOrders = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'READY'])
            ->where('deadline', '<', $now)
            ->limit(5)
            ->get();

        foreach ($overdueOrders as $o) {
            $alerts[] = [
                'id' => "overdue_{$o->id}",
                'type' => 'OVERDUE_ORDER',
                'title' => 'Pesanan Jatuh Tempo!',
                'message' => "Pesanan #{$o->order_number} telah melewati tenggat waktu.",
                'read' => false,
                'created_at' => $o->updated_at ? $o->updated_at->toIso8601String() : $now->toIso8601String(),
                'action_url' => "/orders/{$o->id}",
            ];
        }

        // 3. Upcoming Deadlines (Due in 48h)
        $upcomingOrders = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->whereBetween('deadline', [$now, $twoDaysLater])
            ->limit(5)
            ->get();

        foreach ($upcomingOrders as $o) {
            $alerts[] = [
                'id' => "upcoming_{$o->id}",
                'type' => 'UPCOMING_DEADLINE',
                'title' => 'Tenggat Pesanan Dekat',
                'message' => "Pesanan #{$o->order_number} jatuh tempo pada " . Carbon::parse($o->deadline)->format('d M Y'),
                'read' => false,
                'created_at' => $o->created_at ? $o->created_at->toIso8601String() : $now->toIso8601String(),
                'action_url' => "/orders/{$o->id}",
            ];
        }

        return $alerts;
    }
}
