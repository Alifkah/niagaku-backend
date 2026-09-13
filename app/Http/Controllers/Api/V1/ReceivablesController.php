<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ReceivablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceivablesController extends Controller
{
    public function __construct(protected ReceivablesService $receivablesService)
    {
    }

    /**
     * Get receivables summary and unpaid orders list
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $receivables = $this->receivablesService->getReceivablesData($activeBusiness->id);

        return response()->json([
            'success' => true,
            'data' => $receivables,
        ]);
    }
}
