<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Get paginated expenses for active business
     */
    public function getPaginatedExpenses(
        ?string $search = null,
        ?string $category = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Expense::query();

        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('category', $category);
        }

        return $query->latest('date')->paginate($perPage);
    }

    /**
     * Create expense and audit log
     */
    public function createExpense(array $data, Business $business, ?User $user = null): Expense
    {
        return DB::transaction(function () use ($data, $business, $user) {
            $expense = Expense::create([
                'business_id' => $business->id,
                'description' => $data['description'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'date' => $data['date'] ?? now(),
                'payment_method' => $data['payment_method'] ?? 'CASH',
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLog::create([
                'business_id' => $business->id,
                'user_id' => $user?->id,
                'action' => 'CREATE_EXPENSE',
                'auditable_type' => 'Expense',
                'auditable_id' => $expense->id,
                'payload' => [
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'category' => $expense->category,
                ],
            ]);

            return $expense;
        });
    }

    /**
     * Update expense and audit log
     */
    public function updateExpense(Expense $expense, array $data, ?User $user = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $user) {
            $oldData = $expense->toArray();

            $expense->update([
                'description' => $data['description'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'date' => $data['date'] ?? $expense->date,
                'payment_method' => $data['payment_method'] ?? $expense->payment_method,
                'notes' => $data['notes'] ?? $expense->notes,
            ]);

            AuditLog::create([
                'business_id' => $expense->business_id,
                'user_id' => $user?->id,
                'action' => 'UPDATE_EXPENSE',
                'auditable_type' => 'Expense',
                'auditable_id' => $expense->id,
                'payload' => [
                    'before' => $oldData,
                    'after' => $expense->toArray(),
                ],
            ]);

            return $expense;
        });
    }

    /**
     * Delete expense and audit log
     */
    public function deleteExpense(Expense $expense, ?User $user = null): bool
    {
        return DB::transaction(function () use ($expense, $user) {
            AuditLog::create([
                'business_id' => $expense->business_id,
                'user_id' => $user?->id,
                'action' => 'DELETE_EXPENSE',
                'auditable_type' => 'Expense',
                'auditable_id' => $expense->id,
                'payload' => [
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                ],
            ]);

            return $expense->delete();
        });
    }
}
