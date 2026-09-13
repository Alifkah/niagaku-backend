<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BusinessService
{
    /**
     * Create a business and assign membership inside a DB transaction
     */
    public function createBusiness(User $user, array $data, string $role = 'OWNER'): Business
    {
        return DB::transaction(function () use ($user, $data, $role) {
            $business = Business::create([
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
            ]);

            BusinessUser::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => $role,
                'is_active' => true,
            ]);

            return $business;
        });
    }

    /**
     * Resolve active business and member role for authenticated user
     */
    public function resolveActiveBusiness(User $user, ?string $headerBusinessId = null): array
    {
        $query = BusinessUser::with('business')
            ->where('user_id', $user->id)
            ->where('is_active', true);

        if ($headerBusinessId) {
            $query->where('business_id', $headerBusinessId);
        }

        $membership = $query->first();

        if (! $membership) {
            if ($headerBusinessId) {
                throw new AccessDeniedHttpException('Anda tidak memiliki akses ke usaha ini.');
            }
            return [
                'business' => null,
                'role' => null,
            ];
        }

        return [
            'business' => $membership->business,
            'role' => $membership->role,
        ];
    }
}
