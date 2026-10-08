<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'mysql') {
            DB::table('products')->where('unit', 'pieces')->update(['unit' => 'pcs']);

            return;
        }

        DB::statement('
            ALTER TABLE products
            DROP CONSTRAINT IF EXISTS products_unit_check
        ');

        DB::statement("
            UPDATE products
            SET unit = 'pcs'
            WHERE unit = 'pieces'
        ");

        DB::statement("
            ALTER TABLE products
            ADD CONSTRAINT products_unit_check
            CHECK (
                unit IN (
                    'pcs',
                    'liter',
                    'gram',
                    'kg',
                    'job',
                    'hour',
                    'day',
                    'sqm',
                    'set'
                )
            )
        ");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'mysql') {
            DB::table('products')->where('unit', 'pcs')->update(['unit' => 'pieces']);

            return;
        }

        DB::statement('
            ALTER TABLE products
            DROP CONSTRAINT IF EXISTS products_unit_check
        ');

        DB::statement("
            UPDATE products
            SET unit = 'pieces'
            WHERE unit = 'pcs'
        ");

        DB::statement("
            ALTER TABLE products
            ADD CONSTRAINT products_unit_check
            CHECK (
                unit IN (
                    'pieces',
                    'liter',
                    'gram',
                    'kg',
                    'job',
                    'hour',
                    'day',
                    'sqm',
                    'set'
                )
            )
        ");
    }
};
