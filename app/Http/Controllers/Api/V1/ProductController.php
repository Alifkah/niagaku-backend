<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ProductRequest;
use App\Http\Requests\Master\StockAdjustmentRequest;
use App\Models\Business;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected SubscriptionService $subscriptionService
    ) {
    }

    protected function checkTenantAccess(Request $request, Product $product): void
    {
        $activeBusiness = $request->attributes->get('active_business');
        if (! $activeBusiness || $product->business_id !== $activeBusiness->id) {
            abort(404, 'Produk tidak ditemukan.');
        }
    }

    /**
     * List products with pagination, search, category & low stock filters
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $lowStockOnly = $request->boolean('low_stock');
        $perPage = (int) $request->query('per_page', 15);

        $products = $this->productService->getPaginatedProducts($search, $categoryId, $lowStockOnly, $perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create product with subscription plan limit check
     */
    public function store(ProductRequest $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        if (! $this->subscriptionService->canCreateProduct($activeBusiness->id)) {
            $usage = $this->subscriptionService->getSubscriptionUsage($activeBusiness->id);
            return response()->json([
                'success' => false,
                'error' => 'LIMIT_REACHED',
                'message' => "Batas maksimal {$usage['usage']['products']['max']} produk untuk paket {$usage['plan']['name']} telah tercapai. Silakan upgrade paket Anda.",
                'limit_details' => $usage['usage']['products'],
            ], 403);
        }

        $product = $this->productService->createProduct($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data' => [
                'product' => $product,
            ],
        ], 201);
    }

    /**
     * Product details with inventory movement history
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->checkTenantAccess($request, $product);

        $details = $this->productService->getProductDetails($product);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $details,
            ],
        ]);
    }

    /**
     * Update product details
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->checkTenantAccess($request, $product);

        $updated = $this->productService->updateProduct($product, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data produk berhasil diperbarui.',
            'data' => [
                'product' => $updated,
            ],
        ]);
    }

    /**
     * Record stock adjustment and inventory movement
     */
    public function adjustStock(StockAdjustmentRequest $request, Product $product): JsonResponse
    {
        $this->checkTenantAccess($request, $product);

        $updated = $this->productService->adjustStock(
            $product,
            $request->integer('quantity_change'),
            $request->string('type'),
            $request->input('notes'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Penyesuaian stok produk berhasil dicatat.',
            'data' => [
                'product' => $updated,
            ],
        ]);
    }

    /**
     * Delete product
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->checkTenantAccess($request, $product);

        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
