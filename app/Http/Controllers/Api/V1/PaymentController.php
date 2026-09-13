<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreatePaymentRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
    }

    protected function checkOrderTenantAccess(Request $request, Order $order): void
    {
        $activeBusiness = $request->attributes->get('active_business');
        if (! $activeBusiness || $order->business_id !== $activeBusiness->id) {
            abort(404, 'Pesanan tidak ditemukan.');
        }
    }

    /**
     * List all payments for active business
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $payments = $this->paymentService->getPaginatedPayments(null, $perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * List payments for a specific order
     */
    public function indexForOrder(Request $request, Order $order): JsonResponse
    {
        $this->checkOrderTenantAccess($request, $order);

        $perPage = (int) $request->query('per_page', 15);
        $payments = $this->paymentService->getPaginatedPayments($order->id, $perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Record payment for an order
     */
    public function storeForOrder(CreatePaymentRequest $request, Order $order): JsonResponse
    {
        $this->checkOrderTenantAccess($request, $order);

        $payment = $this->paymentService->recordPayment($order, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => [
                'payment' => $payment,
            ],
        ], 201);
    }
}
