<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Gateways\MockMidtransPaymentGateway;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected MockMidtransPaymentGateway $paymentGateway
    ) {
    }

    /**
     * Get list of available SaaS plans
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('price_monthly', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans,
            ],
        ]);
    }

    /**
     * Get current active business subscription details and usage limits
     */
    public function current(Request $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $usage = $this->subscriptionService->getSubscriptionUsage($activeBusiness->id);

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Initiate plan upgrade checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
        ]);

        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');
        $plan = Plan::where('code', $request->input('plan_code'))->firstOrFail();

        // If upgrading to FREE plan, activate immediately
        if ($plan->code === 'FREE') {
            $sub = $this->subscriptionService->activatePlan($activeBusiness->id, $plan, 'FREE_PLAN_ACTIVATION');
            return response()->json([
                'success' => true,
                'message' => 'Paket Gratis berhasil diaktifkan.',
                'data' => [
                    'subscription' => $sub,
                ],
            ]);
        }

        // Generate payment gateway checkout
        $checkout = $this->paymentGateway->createSubscriptionInvoice($activeBusiness, $plan);

        return response()->json([
            'success' => true,
            'message' => 'Invoice pembayaran berhasil dibuat.',
            'data' => [
                'checkout' => $checkout,
                'plan' => $plan,
            ],
        ]);
    }

    /**
     * Payment Gateway Webhook Listener
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Signature', $request->input('signature_key', 'mock_sig'));
        $payload = $request->all();

        if (! $this->paymentGateway->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $businessId = $request->input('business_id');
        $planCode = $request->input('plan_code', 'STARTER');
        $reference = $request->input('order_id', 'WEBHOOK-' . time());

        $business = Business::find($businessId);
        $plan = Plan::where('code', $planCode)->first();

        if ($business && $plan) {
            $this->subscriptionService->activatePlan($business->id, $plan, $reference, 'MIDTRANS');
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
        ]);
    }
}
