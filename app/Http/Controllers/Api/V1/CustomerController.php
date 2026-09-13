<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService)
    {
    }

    protected function checkTenantAccess(Request $request, Customer $customer): void
    {
        $activeBusiness = $request->attributes->get('active_business');
        if (! $activeBusiness || $customer->business_id !== $activeBusiness->id) {
            abort(404, 'Pelanggan tidak ditemukan.');
        }
    }

    /**
     * List customers with pagination & search
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 15);

        $customers = $this->customerService->getPaginatedCustomers($search, $perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Create new customer
     */
    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->createCustomer($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data pelanggan berhasil ditambahkan.',
            'data' => [
                'customer' => $customer,
            ],
        ], 201);
    }

    /**
     * Customer details
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->checkTenantAccess($request, $customer);

        $details = $this->customerService->getCustomerDetails($customer);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Update customer
     */
    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->checkTenantAccess($request, $customer);

        $updated = $this->customerService->updateCustomer($customer, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data pelanggan berhasil diperbarui.',
            'data' => [
                'customer' => $updated,
            ],
        ]);
    }

    /**
     * Delete customer
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->checkTenantAccess($request, $customer);

        $this->customerService->deleteCustomer($customer);

        return response()->json([
            'success' => true,
            'message' => 'Pelanggan berhasil dihapus.',
        ]);
    }
}
