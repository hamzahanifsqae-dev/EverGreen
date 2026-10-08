<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_category_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_sub_category_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_brand_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_brand_model_id_foreign');

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE SET NULL');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sub_category_id_foreign FOREIGN KEY (sub_category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE SET NULL');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_id_foreign FOREIGN KEY (brand_id) REFERENCES brands(id) ON UPDATE CASCADE ON DELETE SET NULL');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_model_id_foreign FOREIGN KEY (brand_model_id) REFERENCES brand_models(id) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_category_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_sub_category_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_brand_id_foreign');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_brand_model_id_foreign');

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sub_category_id_foreign FOREIGN KEY (sub_category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_id_foreign FOREIGN KEY (brand_id) REFERENCES brands(id) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_model_id_foreign FOREIGN KEY (brand_model_id) REFERENCES brand_models(id) ON UPDATE CASCADE ON DELETE CASCADE');
    }
};
