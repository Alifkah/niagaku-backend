<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Get paginated orders with filters
     */
    public function getPaginatedOrders(
        ?string $search = null,
        ?string $status = null,
        ?string $customerId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Order::with(['customer', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Generate unique sequential order number per business
     */
    public function generateOrderNumber(string $businessId): string
    {
        $todayStr = now()->format('Ymd');
        $prefix = "ORD-{$todayStr}-";

        $latestOrder = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $latestOrder) {
            return $prefix . '0001';
        }

        $lastSeq = (int) substr($latestOrder->order_number, -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);

        return $prefix . $nextSeq;
    }

    /**
     * Create order inside a database transaction
     */
    public function createOrder(array $data, Business $business, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $business, $user) {
            // 1. Validate customer belongs to active business if provided
            if (! empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
                if (! $customer || $customer->business_id !== $business->id) {
                    abort(422, 'Pelanggan tidak valid atau tidak ditemukan dalam usaha ini.');
                }
            }

            // 2. Validate products & calculate totals
            $rawItems = $data['items'];
            $preparedItems = [];
            $calculatedSubtotal = 0.00;

            foreach ($rawItems as $rawItem) {
                $product = Product::find($rawItem['product_id']);
                if (! $product || $product->business_id !== $business->id) {
                    abort(422, "Produk dengan ID {$rawItem['product_id']} tidak valid dalam usaha ini.");
                }

                $quantity = (int) $rawItem['quantity'];
                $unitPrice = isset($rawItem['unit_price']) ? (float) $rawItem['unit_price'] : (float) $product->selling_price;
                $costPriceSnapshot = (float) $product->cost_price; // IMPORTANT: HPP Snapshot
                $itemSubtotal = $unitPrice * $quantity;

                $calculatedSubtotal += $itemSubtotal;

                $preparedItems[] = [
                    'product' => $product,
                    'product_name_snapshot' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost_price_snapshot' => $costPriceSnapshot,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discount = isset($data['discount']) ? (float) $data['discount'] : 0.00;
            $total = max(0.00, $calculatedSubtotal - $discount);
            $paidAmount = isset($data['paid_amount']) ? (float) $data['paid_amount'] : 0.00;

            $paymentStatus = 'UNPAID';
            if ($paidAmount >= $total && $total > 0) {
                $paymentStatus = 'PAID';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'PARTIAL';
            }

            $orderNumber = $this->generateOrderNumber($business->id);

            // 3. Create Order
            $order = Order::create([
                'business_id' => $business->id,
                'customer_id' => $data['customer_id'] ?? null,
                'order_number' => $orderNumber,
                'order_date' => $data['order_date'] ?? now(),
                'deadline' => $data['deadline'] ?? null,
                'status' => $data['status'] ?? 'PENDING',
                'subtotal' => $calculatedSubtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
                'notes' => $data['notes'] ?? null,
            ]);

            // 4. Create Order Items & Deduct Stock
            foreach ($preparedItems as $pItem) {
                /** @var Product $product */
                $product = $pItem['product'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $pItem['product_name_snapshot'],
                    'quantity' => $pItem['quantity'],
                    'unit_price' => $pItem['unit_price'],
                    'cost_price_snapshot' => $pItem['cost_price_snapshot'],
                    'subtotal' => $pItem['subtotal'],
                ]);

                // Deduct stock and log movement
                $product->decrement('stock', $pItem['quantity']);

                InventoryMovement::create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'quantity_change' => -$pItem['quantity'],
                    'type' => 'SALE',
                    'notes' => "Penjualan via Pesanan #{$order->order_number}",
                ]);
            }

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Cancel order & restore inventory
     */
    public function cancelOrder(Order $order, ?User $user = null): Order
    {
        return DB::transaction(function () use ($order, $user) {
            if ($order->status === 'CANCELLED') {
                abort(422, 'Pesanan sudah dibatalkan sebelumnya.');
            }

            $order->status = 'CANCELLED';
            $order->save();

            // Restore product stocks
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);

                        InventoryMovement::create([
                            'business_id' => $order->business_id,
                            'product_id' => $product->id,
                            'order_id' => $order->id,
                            'user_id' => $user?->id,
                            'quantity_change' => $item->quantity,
                            'type' => 'ADJUSTMENT',
                            'notes' => "Pengembalian stok pembatalan pesanan #{$order->order_number}",
                        ]);
                    }
                }
            }

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        $order->status = $status;
        $order->save();

        return $order->load(['customer', 'items.product']);
    }
}
