<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->timestamp('date')->useCurrent();
            $table->string('method', 30)->default('CASH'); // CASH, BANK_TRANSFER, E_WALLET, QRIS, OTHER
            $table->string('status', 20)->default('CONFIRMED'); // CONFIRMED, PENDING
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'order_id']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
