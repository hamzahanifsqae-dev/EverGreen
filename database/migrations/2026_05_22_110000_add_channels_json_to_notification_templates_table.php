<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notification_templates', 'channels')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->json('channels')->nullable()->after('events');
            });
        }

        if (Schema::hasColumn('notification_templates', 'channel')) {
            DB::table('notification_templates')
                ->select(['id', 'channel'])
                ->orderBy('id')
                ->each(function (object $row): void {
                    if (! filled($row->channel ?? null)) {
                        return;
                    }

                    DB::table('notification_templates')
                        ->where('id', $row->id)
                        ->update(['channels' => json_encode([$row->channel])]);
                });

            try {
                Schema::table('notification_templates', function (Blueprint $table) {
                    $table->dropIndex('notification_templates_channel_index');
                });
            } catch (Throwable) {
                // Index name may differ by driver.
            }

            Schema::table('notification_templates', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->enum('channel', ['email', 'sms', 'whatsapp'])->after('events');
        });

        DB::table('notification_templates')
            ->select(['id', 'channels'])
            ->orderBy('id')
            ->each(function (object $row): void {
                $decoded = json_decode($row->channels ?? '[]', true);
                $first = is_array($decoded) ? ($decoded[0] ?? 'email') : 'email';

                if (! in_array($first, ['email', 'sms', 'whatsapp'], true)) {
                    $first = 'email';
                }

                DB::table('notification_templates')
                    ->where('id', $row->id)
                    ->update(['channel' => $first]);
            });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn('channels');
        });
    }
};
