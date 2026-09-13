<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('description');
            $table->string('category', 50)->default('OPERATIONAL'); // OPERATIONAL, SALARY, RENT, UTILITIES, RAW_MATERIAL, MARKETING, OTHER
            $table->decimal('amount', 15, 2);
            $table->timestamp('date')->useCurrent();
            $table->string('payment_method', 30)->default('CASH');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index(['business_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
