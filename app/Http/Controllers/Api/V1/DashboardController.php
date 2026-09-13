<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService)
    {
    }

    /**
     * Get business dashboard metrics
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $metrics = $this->dashboardService->getDashboardMetrics($activeBusiness->id);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
}
