<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Opening Balance feature. Opening Stock (the lot/quantity import)
 * is unaffected and keeps its own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cold_storage_opening_balances');
    }

    public function down(): void
    {
        Schema::create('cold_storage_opening_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->date('as_of_date');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->string('import_batch')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'customer_id', 'branch_id'], 'cold_storage_opening_balances_unique');
        });
    }
};
