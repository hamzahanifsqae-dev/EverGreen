<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('merchants', 'primary_contact_number')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->dropUnique(['primary_contact_number']);
            });
        }

        if (Schema::hasColumn('merchants', 'primary_contact_email')) {
            try {
                Schema::table('merchants', function (Blueprint $table) {
                    $table->dropUnique(['primary_contact_email']);
                });
            } catch (Throwable) {
                // Index may not exist on some databases.
            }
        }

        Schema::table('merchants', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('merchants', 'primary_contact_name')) {
                $columns[] = 'primary_contact_name';
            }

            if (Schema::hasColumn('merchants', 'primary_contact_number')) {
                $columns[] = 'primary_contact_number';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        if (Schema::hasColumn('merchants', 'primary_contact_email')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->renameColumn('primary_contact_email', 'email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_number')->nullable()->unique();
            $table->renameColumn('email', 'primary_contact_email');
        });
    }
};
