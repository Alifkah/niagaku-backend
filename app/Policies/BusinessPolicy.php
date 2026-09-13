<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Helper to get user's role in the specified business
     */
    protected function getUserRole(User $user, Business $business): ?string
    {
        $membership = BusinessUser::where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->first();

        return $membership?->role;
    }

    /**
     * Determine if user belongs to the business
     */
    public function view(User $user, Business $business): bool
    {
        return $this->getUserRole($user, $business) !== null;
    }

    /**
     * Determine if user can update business profile/settings (OWNER only)
     */
    public function update(User $user, Business $business): bool
    {
        return $this->getUserRole($user, $business) === 'OWNER';
    }

    /**
     * Determine if user can view financial statements & profit reports (OWNER only)
     */
    public function viewFinancials(User $user, Business $business): bool
    {
        return $this->getUserRole($user, $business) === 'OWNER';
    }

    /**
     * Determine if user can manage subscription & billing (OWNER only)
     */
    public function manageSubscription(User $user, Business $business): bool
    {
        return $this->getUserRole($user, $business) === 'OWNER';
    }

    /**
     * Determine if user can manage team members (OWNER only)
     */
    public function manageTeam(User $user, Business $business): bool
    {
        return $this->getUserRole($user, $business) === 'OWNER';
    }
}
