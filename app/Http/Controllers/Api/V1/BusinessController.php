<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\OnboardingRequest;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BusinessController extends Controller
{
    public function __construct(protected BusinessService $businessService)
    {
    }

    /**
     * Create initial business / complete onboarding
     */
    public function store(OnboardingRequest $request): JsonResponse
    {
        $business = $this->businessService->createBusiness(
            $request->user(),
            $request->validated(),
            'OWNER'
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil usaha berhasil dibuat.',
            'data' => [
                'business' => $business,
                'role' => 'OWNER',
            ],
        ], 201);
    }

    /**
     * Get active business details
     */
    public function show(Request $request): JsonResponse
    {
        $business = $request->attributes->get('active_business');
        $role = $request->attributes->get('active_role');

        return response()->json([
            'success' => true,
            'data' => [
                'business' => $business,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Update business profile (OWNER only)
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('active_business');

        if (! Gate::allows('update', $business)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Pemilik Usaha (OWNER) yang dapat mengubah pengaturan usaha.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $business->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil usaha berhasil diperbarui.',
            'data' => [
                'business' => $business,
            ],
        ]);
    }

    /**
     * Owner-only financial gate check endpoint
     */
    public function financialAccessCheck(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('active_business');

        if (! Gate::allows('viewFinancials', $business)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Informasi keuangan hanya dapat diakses oleh Pemilik Usaha (OWNER).',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akses keuangan diizinkan.',
        ]);
    }
}
