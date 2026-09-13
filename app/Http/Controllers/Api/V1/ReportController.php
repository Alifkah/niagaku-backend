<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    /**
     * Get aggregated business reports (sales, profit, expenses)
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $preset = $request->query('preset', 'this_month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        [$start, $end] = $this->reportService->resolveDateRange($preset, $startDate, $endDate);

        $salesReport = $this->reportService->getSalesReport($activeBusiness->id, $start, $end);
        $profitReport = $this->reportService->getProfitReport($activeBusiness->id, $start, $end);
        $expenseReport = $this->reportService->getExpenseReport($activeBusiness->id, $start, $end);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'preset' => $preset,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
                'sales_report' => $salesReport,
                'profit_report' => $profitReport,
                'expense_report' => $expenseReport,
            ],
        ]);
    }

    /**
     * Download CSV report export
     */
    public function export(Request $request): StreamedResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $type = $request->query('type', 'profit');
        $preset = $request->query('preset', 'this_month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        [$start, $end] = $this->reportService->resolveDateRange($preset, $startDate, $endDate);

        $csvData = $this->reportService->generateCsvExport($activeBusiness->id, $type, $start, $end);
        $filename = "laporan_{$type}_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
