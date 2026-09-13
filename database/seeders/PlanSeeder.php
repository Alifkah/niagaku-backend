<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['code' => 'FREE'], [
            'name' => 'Gratis (Free)',
            'price_monthly' => 0,
            'max_orders_per_month' => 50,
            'max_products' => 20,
            'max_users' => 1,
            'features' => ['50 pesanan / bulan', '20 produk', '1 pengguna', 'Dashboard standar'],
            'is_active' => true,
        ]);

        Plan::updateOrCreate(['code' => 'STARTER'], [
            'name' => 'Starter UMKM',
            'price_monthly' => 49000,
            'max_orders_per_month' => null, // Unlimited
            'max_products' => 500,
            'max_users' => 2,
            'features' => ['Pesanan tanpa batas', '500 produk', '2 pengguna', 'Keuangan & Pengeluaran', 'Laporan & Ekspor CSV'],
            'is_active' => true,
        ]);

        Plan::updateOrCreate(['code' => 'BUSINESS'], [
            'name' => 'Business Pro',
            'price_monthly' => 99000,
            'max_orders_per_month' => null, // Unlimited
            'max_products' => null, // Unlimited
            'max_users' => 5,
            'features' => ['Pesanan tanpa batas', 'Produk tanpa batas', '5 pengguna', 'Analitik Lanjutan', 'NiagaKu AI Assistant', 'Notifikasi Otomatis'],
            'is_active' => true,
        ]);

        Plan::updateOrCreate(['code' => 'PRO'], [
            'name' => 'Enterprise Pro',
            'price_monthly' => 199000,
            'max_orders_per_month' => null,
            'max_products' => null,
            'max_users' => null,
            'features' => ['Multi-Outlet', 'Pengguna tanpa batas', 'AI Analytics', 'Akses REST API', 'Dukungan Prioritas 24/7'],
            'is_active' => true,
        ]);
    }
}
