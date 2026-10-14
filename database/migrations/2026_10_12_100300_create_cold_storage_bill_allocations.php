<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'unallocated_amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('unallocated_amount', 14, 2)->default(0)->after('amount');
            });
        }

        if (Schema::hasTable('cold_storage_bill_allocations')) {
            return;
        }

        Schema::create('cold_storage_bill_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bill_id')->constrained('cold_storage_bills')->restrictOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('allocated_on');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['bill_id']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_bill_allocations');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('unallocated_amount');
        });
    }
};
