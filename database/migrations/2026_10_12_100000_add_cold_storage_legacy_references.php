<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('account_code')->nullable()->after('reference');
            $table->unique(['merchant_id', 'account_code'], 'customers_merchant_account_code_unique');
        });

        Schema::create('cold_storage_customer_legacy_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('season')->nullable();
            $table->string('book')->nullable();
            $table->string('page')->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'customer_id']);
        });

        Schema::table('cold_storage_receipts', function (Blueprint $table) {
            $table->string('legacy_reference')->nullable()->after('receipt_no');
        });

        Schema::table('cold_storage_dispatches', function (Blueprint $table) {
            $table->string('legacy_reference')->nullable()->after('dispatch_no');
        });

        Schema::table('cold_storage_receipt_items', function (Blueprint $table) {
            $table->string('marks')->nullable()->after('lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('cold_storage_receipt_items', function (Blueprint $table) {
            $table->dropColumn('marks');
        });

        Schema::table('cold_storage_dispatches', function (Blueprint $table) {
            $table->dropColumn('legacy_reference');
        });

        Schema::table('cold_storage_receipts', function (Blueprint $table) {
            $table->dropColumn('legacy_reference');
        });

        Schema::dropIfExists('cold_storage_customer_legacy_references');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_merchant_account_code_unique');
            $table->dropColumn('account_code');
        });
    }
};
