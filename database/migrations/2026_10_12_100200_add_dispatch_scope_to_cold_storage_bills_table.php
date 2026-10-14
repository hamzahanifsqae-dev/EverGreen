<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cold_storage_bills', function (Blueprint $table) {
            $table->foreignUuid('dispatch_id')->nullable()->after('customer_id')->constrained('cold_storage_dispatches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cold_storage_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatch_id');
        });
    }
};
