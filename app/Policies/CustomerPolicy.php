<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Verify user belongs to the same business as the customer
     */
    public function view(User $user, Customer $customer): bool
    {
        $activeBusiness = request()->attributes->get('active_business');
        return $activeBusiness && $customer->business_id === $activeBusiness->id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
