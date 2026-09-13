<?php

namespace App\Http\Middleware;

use App\Services\BusinessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureActiveBusiness
{
    public function __construct(protected BusinessService $businessService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $headerBusinessId = $request->header('X-Business-ID');

        try {
            $activeContext = $this->businessService->resolveActiveBusiness($user, $headerBusinessId);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        if (! $activeContext['business']) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada usaha yang terhubung dengan akun ini. Silakan selesaikan pendaftaran usaha.',
                'require_onboarding' => true,
            ], 403);
        }

        $request->attributes->set('active_business', $activeContext['business']);
        $request->attributes->set('active_role', $activeContext['role']);

        return $next($request);
    }
}
