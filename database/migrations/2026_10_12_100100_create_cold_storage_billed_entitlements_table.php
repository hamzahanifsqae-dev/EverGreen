<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_storage_billed_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_line_id')->constrained('cold_storage_bill_lines')->cascadeOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->index(['receipt_item_id', 'occurred_on'], 'cold_storage_billed_entitlements_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_billed_entitlements');
    }
};
