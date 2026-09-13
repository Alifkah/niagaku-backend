<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Business;
use App\Models\Plan;
use Illuminate\Support\Str;

class MockMidtransPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Generate mock Midtrans checkout invoice redirect URL
     */
    public function createSubscriptionInvoice(Business $business, Plan $plan): array
    {
        $orderId = 'SUB-' . Str::upper(Str::random(10));
        $paymentUrl = "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$orderId}";

        return [
            'success' => true,
            'gateway' => 'MIDTRANS',
            'order_id' => $orderId,
            'payment_url' => $paymentUrl,
            'amount' => $plan->price_monthly,
            'status' => 'PENDING',
        ];
    }

    /**
     * Verify webhook signature for development/testing
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // For development/mock testing, verify non-empty signature or test token
        return !empty($signature);
    }
}
