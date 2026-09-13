<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('order_number', 50);
            $table->timestamp('order_date')->useCurrent();
            $table->timestamp('deadline')->nullable();
            $table->string('status', 20)->default('PENDING'); // DRAFT, PENDING, PROCESSING, READY, COMPLETED, CANCELLED
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->string('payment_status', 20)->default('UNPAID'); // UNPAID, PARTIAL, PAID
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'order_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
