<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_storage_alert_email_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('recipient_emails')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('include_temperature')->default(true);
            $table->boolean('include_bills')->default(true);
            $table->boolean('include_reservations')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_alert_email_settings');
    }
};
