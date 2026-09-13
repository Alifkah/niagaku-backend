<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /**
     * Get or initialize active subscription for a business
     */
    public function getActiveSubscription(string $businessId): Subscription
    {
        $subscription = Subscription::where('business_id', $businessId)
            ->where('status', 'ACTIVE')
            ->with('plan')
            ->latest()
            ->first();

        if (!$subscription) {
            // Assign default FREE plan
            $freePlan = Plan::where('code', 'FREE')->first();
            if (!$freePlan) {
                $freePlan = Plan::create([
                    'code' => 'FREE',
                    'name' => 'Gratis (Free)',
                    'price_monthly' => 0,
                    'max_orders_per_month' => 50,
                    'max_products' => 20,
                    'max_users' => 1,
                    'features' => ['50 pesanan / bulan', '20 produk', '1 pengguna'],
                ]);
            }

            $subscription = Subscription::create([
                'business_id' => $businessId,
                'plan_id' => $freePlan->id,
                'status' => 'ACTIVE',
                'starts_at' => now(),
                'ends_at' => null,
            ]);

            $subscription->load('plan');
        }

        return $subscription;
    }

    /**
     * Get comprehensive usage metrics and plan limits
     */
    public function getSubscriptionUsage(string $businessId): array
    {
        $sub = $this->getActiveSubscription($businessId);
        $plan = $sub->plan;

        // Current Products Count
        $productsCount = Product::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->count();

        // Current Orders This Month Count
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $ordersThisMonthCount = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Current Team Members Count
        $usersCount = BusinessUser::where('business_id', $businessId)->count();

        return [
            'subscription_id' => $sub->id,
            'status' => $sub->status,
            'starts_at' => $sub->starts_at ? $sub->starts_at->toIso8601String() : null,
            'ends_at' => $sub->ends_at ? $sub->ends_at->toIso8601String() : null,
            'plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'features' => $plan->features,
            ],
            'usage' => [
                'products' => [
                    'current' => $productsCount,
                    'max' => $plan->max_products,
                    'is_limit_reached' => $plan->max_products !== null && $productsCount >= $plan->max_products,
                ],
                'orders_this_month' => [
                    'current' => $ordersThisMonthCount,
                    'max' => $plan->max_orders_per_month,
                    'is_limit_reached' => $plan->max_orders_per_month !== null && $ordersThisMonthCount >= $plan->max_orders_per_month,
                ],
                'users' => [
                    'current' => $usersCount,
                    'max' => $plan->max_users,
                    'is_limit_reached' => $plan->max_users !== null && $usersCount >= $plan->max_users,
                ],
            ],
        ];
    }

    /**
     * Assert whether business can create a product under active plan limits
     */
    public function canCreateProduct(string $businessId): bool
    {
        $sub = $this->getActiveSubscription($businessId);
        $maxProducts = $sub->plan->max_products;

        if ($maxProducts === null) {
            return true;
        }

        $count = Product::withoutGlobalScopes()->where('business_id', $businessId)->count();
        return $count < $maxProducts;
    }

    /**
     * Assert whether business can create an order under active plan limits
     */
    public function canCreateOrder(string $businessId): bool
    {
        $sub = $this->getActiveSubscription($businessId);
        $maxOrders = $sub->plan->max_orders_per_month;

        if ($maxOrders === null) {
            return true;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $count = Order::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        return $count < $maxOrders;
    }

    /**
     * Activate a new plan subscription for business
     */
    public function activatePlan(string $businessId, Plan $plan, string $reference = 'DIRECT_ACTIVATION', string $gateway = 'MIDTRANS'): Subscription
    {
        // Deactivate existing active subscriptions
        Subscription::where('business_id', $businessId)
            ->where('status', 'ACTIVE')
            ->update(['status' => 'CANCELLED']);

        $subscription = Subscription::create([
            'business_id' => $businessId,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => $plan->code === 'FREE' ? null : now()->addDays(30),
            'payment_gateway' => $gateway,
            'payment_reference' => $reference,
        ]);

        AuditLog::create([
            'business_id' => $businessId,
            'user_id' => auth()->id(),
            'action' => 'UPGRADE_SUBSCRIPTION',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
            'payload' => ['plan_code' => $plan->code, 'plan_name' => $plan->name, 'reference' => $reference],
        ]);

        return $subscription;
    }
}
