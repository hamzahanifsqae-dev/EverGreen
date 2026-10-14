<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Temperature monitoring feature: the readings table, the chamber
 * columns that only existed to support it, and the digest-email toggle for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cold_storage_temperature_readings');

        if (Schema::hasColumn('cold_storage_chambers', 'min_temperature')
            || Schema::hasColumn('cold_storage_chambers', 'max_temperature')
            || Schema::hasColumn('cold_storage_chambers', 'temperature_unit')) {
            Schema::table('cold_storage_chambers', function (Blueprint $table) {
                $columns = array_filter(
                    ['min_temperature', 'max_temperature', 'temperature_unit'],
                    fn (string $column) => Schema::hasColumn('cold_storage_chambers', $column)
                );

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasColumn('cold_storage_alert_email_settings', 'include_temperature')) {
            Schema::table('cold_storage_alert_email_settings', function (Blueprint $table) {
                $table->dropColumn('include_temperature');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cold_storage_alert_email_settings', function (Blueprint $table) {
            $table->boolean('include_temperature')->default(true)->after('recipient_emails');
        });

        Schema::table('cold_storage_chambers', function (Blueprint $table) {
            $table->decimal('min_temperature', 8, 2)->nullable();
            $table->decimal('max_temperature', 8, 2)->nullable();
            $table->string('temperature_unit', 8)->default('C');
        });

        Schema::create('cold_storage_temperature_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->cascadeOnDelete();
            $table->dateTime('recorded_at');
            $table->decimal('temperature', 8, 2);
            $table->string('temperature_unit', 8)->default('C');
            $table->decimal('min_temperature', 8, 2)->nullable();
            $table->decimal('max_temperature', 8, 2)->nullable();
            $table->boolean('is_out_of_range')->default(false);
            $table->string('source')->default('manual');
            $table->string('sensor_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chamber_id', 'recorded_at']);
        });
    }
};
