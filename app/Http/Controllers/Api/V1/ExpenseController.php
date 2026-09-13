<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Models\Business;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenseService)
    {
    }

    protected function checkTenantAccess(Request $request, Expense $expense): void
    {
        $activeBusiness = $request->attributes->get('active_business');
        if (! $activeBusiness || $expense->business_id !== $activeBusiness->id) {
            abort(404, 'Pengeluaran tidak ditemukan.');
        }
    }

    /**
     * List expenses
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $perPage = (int) $request->query('per_page', 15);

        $expenses = $this->expenseService->getPaginatedExpenses($search, $category, $perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    /**
     * Create expense
     */
    public function store(ExpenseRequest $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $expense = $this->expenseService->createExpense(
            $request->validated(),
            $activeBusiness,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengeluaran berhasil dicatat.',
            'data' => [
                'expense' => $expense,
            ],
        ], 201);
    }

    /**
     * Expense detail
     */
    public function show(Request $request, Expense $expense): JsonResponse
    {
        $this->checkTenantAccess($request, $expense);

        return response()->json([
            'success' => true,
            'data' => [
                'expense' => $expense,
            ],
        ]);
    }

    /**
     * Update expense
     */
    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->checkTenantAccess($request, $expense);

        $updated = $this->expenseService->updateExpense($expense, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengeluaran berhasil diperbarui.',
            'data' => [
                'expense' => $updated,
            ],
        ]);
    }

    /**
     * Delete expense
     */
    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->checkTenantAccess($request, $expense);

        $this->expenseService->deleteExpense($expense, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengeluaran berhasil dihapus.',
        ]);
    }
}
