<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Get paginated products with category, search, and low stock filters
     */
    public function getPaginatedProducts(
        ?string $search = null,
        ?string $categoryId = null,
        ?bool $lowStockOnly = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Product::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($lowStockOnly) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create product and record initial inventory movement if stock > 0
     */
    public function createProduct(array $data, ?User $user = null): Product
    {
        return DB::transaction(function () use ($data, $user) {
            $product = Product::create($data);

            $initialStock = $product->stock ?? 0;
            if ($initialStock > 0) {
                InventoryMovement::create([
                    'business_id' => $product->business_id,
                    'product_id' => $product->id,
                    'user_id' => $user?->id,
                    'quantity_change' => $initialStock,
                    'type' => 'IN',
                    'notes' => 'Stok awal saat pembuatan produk',
                ]);
            }

            return $product->load('category');
        });
    }

    /**
     * Update product details
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->load('category');
    }

    /**
     * Perform stock adjustment and log inventory movement
     */
    public function adjustStock(
        Product $product,
        int $quantityChange,
        string $type,
        ?string $notes = null,
        ?User $user = null
    ): Product {
        return DB::transaction(function () use ($product, $quantityChange, $type, $notes, $user) {
            $product->stock += $quantityChange;
            $product->save();

            InventoryMovement::create([
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'user_id' => $user?->id,
                'quantity_change' => $quantityChange,
                'type' => $type,
                'notes' => $notes ?? 'Penyesuaian stok manual',
            ]);

            return $product->load(['category', 'inventoryMovements']);
        });
    }

    /**
     * Get detailed product info with inventory movement history
     */
    public function getProductDetails(Product $product): Product
    {
        return $product->load(['category', 'inventoryMovements.user']);
    }

    /**
     * Delete product
     */
    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }
}
