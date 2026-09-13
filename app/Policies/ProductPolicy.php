<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Verify user belongs to the same business as the product
     */
    public function view(User $user, Product $product): bool
    {
        $activeBusiness = request()->attributes->get('active_business');
        return $activeBusiness && $product->business_id === $activeBusiness->id;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
