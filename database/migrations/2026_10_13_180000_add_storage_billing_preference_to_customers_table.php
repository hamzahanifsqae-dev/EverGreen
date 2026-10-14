<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Informational only: which billing workflow a clerk should normally use
            // for this customer. Both "Storage bills > New" (periodic) and "Bill this
            // withdrawal" (on_withdrawal) remain available regardless of this value —
            // it never restricts which action can be taken.
            $table->string('storage_billing_preference')->nullable()->after('account_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('storage_billing_preference');
        });
    }
};
