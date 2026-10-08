<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->dropProductForeignKeys();

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('sub_category_id')
                ->references('id')->on('categories')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('brand_id')
                ->references('id')->on('brands')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('brand_model_id')
                ->references('id')->on('brand_models')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->dropProductForeignKeys();

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('sub_category_id')
                ->references('id')->on('categories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('brand_id')
                ->references('id')->on('brands')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('brand_model_id')
                ->references('id')->on('brand_models')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    private function dropProductForeignKeys(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['category_id', 'sub_category_id', 'brand_id', 'brand_model_id'] as $column) {
                try {
                    $table->dropForeign([$column]);
                } catch (\Throwable) {
                    // Foreign key may already be absent on some drivers/environments.
                }
            }
        });
    }
};
