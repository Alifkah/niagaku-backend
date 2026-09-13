<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // FREE, STARTER, BUSINESS, PRO
            $table->string('name');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->integer('max_orders_per_month')->nullable(); // NULL means unlimited
            $table->integer('max_products')->nullable(); // NULL means unlimited
            $table->integer('max_users')->nullable(); // NULL means unlimited
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
