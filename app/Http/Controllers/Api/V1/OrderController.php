<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Business;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected SubscriptionService $subscriptionService
    ) {
    }

    protected function checkTenantAccess(Request $request, Order $order): void
    {
        $activeBusiness = $request->attributes->get('active_business');
        if (! $activeBusiness || $order->business_id !== $activeBusiness->id) {
            abort(404, 'Pesanan tidak ditemukan.');
        }
    }

    /**
     * List orders with filters & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $perPage = (int) $request->query('per_page', 15);

        $orders = $this->orderService->getPaginatedOrders($search, $status, $customerId, $perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Create order with subscription plan limit check
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        if (! $this->subscriptionService->canCreateOrder($activeBusiness->id)) {
            $usage = $this->subscriptionService->getSubscriptionUsage($activeBusiness->id);
            return response()->json([
                'success' => false,
                'error' => 'LIMIT_REACHED',
                'message' => "Batas maksimal {$usage['usage']['orders_this_month']['max']} order/bulan untuk paket {$usage['plan']['name']} telah tercapai. Silakan upgrade paket Anda.",
                'limit_details' => $usage['usage']['orders_this_month'],
            ], 403);
        }

        $order = $this->orderService->createOrder(
            $request->validated(),
            $activeBusiness,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'data' => [
                'order' => $order,
            ],
        ], 201);
    }

    /**
     * Order detail
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->checkTenantAccess($request, $order);

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order->load(['customer', 'items.product']),
            ],
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->checkTenantAccess($request, $order);

        $cancelled = $this->orderService->cancelOrder($order, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => [
                'order' => $cancelled,
            ],
        ]);
    }

    /**
     * Update status
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->checkTenantAccess($request, $order);

        $updated = $this->orderService->updateOrderStatus($order, $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => [
                'order' => $updated,
            ],
        ]);
    }
}
