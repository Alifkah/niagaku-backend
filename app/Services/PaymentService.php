<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Get paginated payments for active business
     */
    public function getPaginatedPayments(?string $orderId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::with(['order.customer']);

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        return $query->latest('date')->paginate($perPage);
    }

    /**
     * Record payment for an order strictly enforcing no overpayment
     */
    public function recordPayment(Order $order, array $data, ?User $user = null): Payment
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $amount = (float) $data['amount'];
            $currentPaid = (float) $order->paid_amount;
            $orderTotal = (float) $order->total;
            $outstanding = max(0.00, $orderTotal - $currentPaid);

            // IMPORTANT PAYMENT RULE: Reject overpayment
            if ($amount > ($outstanding + 0.01)) {
                $formattedOutstanding = number_format($outstanding, 0, ',', '.');
                abort(422, "Pembayaran (Rp" . number_format($amount, 0, ',', '.') . ") melebihi sisa tagihan (Rp{$formattedOutstanding}). Overpayment tidak diperbolehkan.");
            }

            $status = $data['status'] ?? 'CONFIRMED';

            // 1. Create Payment record
            $payment = Payment::create([
                'business_id' => $order->business_id,
                'order_id' => $order->id,
                'amount' => $amount,
                'date' => $data['date'] ?? now(),
                'method' => $data['method'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            // 2. Recalculate confirmed total paid for the order
            $confirmedPaidSum = (float) Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('status', 'CONFIRMED')
                ->sum('amount');

            $paymentStatus = 'UNPAID';
            if ($confirmedPaidSum >= $orderTotal && $orderTotal > 0) {
                $paymentStatus = 'PAID';
            } elseif ($confirmedPaidSum > 0) {
                $paymentStatus = 'PARTIAL';
            }

            $order->update([
                'paid_amount' => $confirmedPaidSum,
                'payment_status' => $paymentStatus,
            ]);

            // 3. Create Audit Log
            AuditLog::create([
                'business_id' => $order->business_id,
                'user_id' => $user?->id,
                'action' => 'CREATE_PAYMENT',
                'auditable_type' => 'Payment',
                'auditable_id' => $payment->id,
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                    'method' => $payment->method,
                    'new_paid_amount' => $confirmedPaidSum,
                    'new_payment_status' => $paymentStatus,
                ],
            ]);

            return $payment->load('order');
        });
    }
}
