<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded with existence checks: an earlier, differently-tracked deploy
        // already applied some of these changes on at least one environment.
        if (! Schema::hasColumn('customers', 'account_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('account_code')->nullable()->after('reference');
            });
        }

        if (! $this->hasIndex('customers', 'customers_merchant_account_code_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['merchant_id', 'account_code'], 'customers_merchant_account_code_unique');
            });
        }

        if (! Schema::hasTable('cold_storage_customer_legacy_references')) {
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
        }

        if (! Schema::hasColumn('cold_storage_receipts', 'legacy_reference')) {
            Schema::table('cold_storage_receipts', function (Blueprint $table) {
                $table->string('legacy_reference')->nullable()->after('receipt_no');
            });
        }

        if (! Schema::hasColumn('cold_storage_dispatches', 'legacy_reference')) {
            Schema::table('cold_storage_dispatches', function (Blueprint $table) {
                $table->string('legacy_reference')->nullable()->after('dispatch_no');
            });
        }

        if (! Schema::hasColumn('cold_storage_receipt_items', 'marks')) {
            Schema::table('cold_storage_receipt_items', function (Blueprint $table) {
                $table->string('marks')->nullable()->after('lot_number');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list({$table})");

            return collect($indexes)->contains(fn ($index) => $index->name === $indexName);
        }

        $indexes = $connection->select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
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
