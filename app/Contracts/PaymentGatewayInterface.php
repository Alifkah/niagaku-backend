<?php

namespace App\Contracts;

use App\Models\Business;
use App\Models\Plan;

interface PaymentGatewayInterface
{
    /**
     * Create checkout invoice for plan subscription
     */
    public function createSubscriptionInvoice(Business $business, Plan $plan): array;

    /**
     * Verify incoming webhook notification signature
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool;
}
