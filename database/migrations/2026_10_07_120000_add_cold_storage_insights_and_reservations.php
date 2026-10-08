<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cold_storage_temperature_readings', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('is_out_of_range');
            $table->foreignUuid('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('cold_storage_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('chamber_id')->nullable()->constrained('cold_storage_chambers')->nullOnDelete();
            $table->string('reservation_no');
            $table->date('reserved_from');
            $table->date('reserved_until')->nullable();
            $table->decimal('expected_packages', 14, 3)->nullable();
            $table->decimal('expected_weight', 14, 3)->nullable();
            $table->string('weight_unit')->nullable();
            $table->decimal('expected_capacity', 14, 3)->nullable();
            $table->string('capacity_unit')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignUuid('receipt_id')->nullable()->constrained('cold_storage_receipts')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignUuid('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'reservation_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_reservations');

        Schema::table('cold_storage_temperature_readings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn('acknowledged_at');
        });
    }
};
