<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::create('cold_storage_opening_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('lot_number');
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->decimal('package_count', 14, 3);
            $table->decimal('net_weight', 14, 3);
            $table->string('weight_unit');
            $table->date('original_received_on')->nullable();
            $table->date('as_of_date');
            $table->string('import_batch')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('receipt_item_id')->nullable()->constrained('cold_storage_receipt_items')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['merchant_id', 'customer_id', 'lot_number', 'chamber_id', 'location_id', 'as_of_date'],
                'cold_storage_opening_stocks_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_opening_stocks');
        Schema::dropIfExists('cold_storage_opening_balances');
    }
};
