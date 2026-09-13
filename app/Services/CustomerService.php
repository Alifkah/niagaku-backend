<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * Get paginated customer list with optional search query
     */
    public function getPaginatedCustomers(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Get customer details with order history & payment placeholders
     */
    public function getCustomerDetails(Customer $customer): array
    {
        return [
            'customer' => $customer,
            'orders' => [], // To be populated in Phase 4
            'payments' => [], // To be populated in Phase 5
            'outstanding_balance' => 0.00,
        ];
    }

    /**
     * Update existing customer
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer;
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(Customer $customer): bool
    {
        return $customer->delete();
    }
}
