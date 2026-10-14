<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clears all cold storage / transactional data from the database while
 * keeping credentials, roles/permissions, businesses, branches, products,
 * and categories/brands intact. Mirrors the selective clears run against
 * the local dev database throughout this project's build-out.
 *
 * Run with: php artisan app:production-selective-clear --force
 */
class ProductionSelectiveClearCommand extends Command
{
    protected $signature = 'app:production-selective-clear {--force : Skip the confirmation prompt}';

    protected $description = 'Clear all data except credentials, businesses, branches, products, and categories/brands';

    /** @var list<string> */
    private array $tablesToClear = [
        'asset_types', 'assets', 'attachments', 'audits', 'cash_flows',
        'cold_storage_adjustment_lines', 'cold_storage_adjustments', 'cold_storage_alert_email_settings',
        'cold_storage_bill_allocations', 'cold_storage_bill_lines', 'cold_storage_bill_services',
        'cold_storage_billed_entitlements', 'cold_storage_bills', 'cold_storage_chambers',
        'cold_storage_customer_legacy_references', 'cold_storage_dispatch_lines', 'cold_storage_dispatches',
        'cold_storage_locations', 'cold_storage_movements', 'cold_storage_rate_cards',
        'cold_storage_receipt_allocations', 'cold_storage_receipt_items', 'cold_storage_receipts',
        'cold_storage_reservations', 'cold_storage_temperature_readings', 'cold_storage_transfer_lines',
        'cold_storage_transfers', 'credit_reminder_templates', 'credit_reminders', 'customer_branches',
        'customer_businesses', 'customers', 'demo_visitor_sessions', 'expense_items', 'expenses',
        'invoice_dynamic_fields', 'invoice_dynamic_groups', 'merchant_credit_reminder_settings',
        'notification_templates', 'orders', 'outbound_messages', 'payments', 'payrolls',
        'purchase_item_variants', 'purchase_items', 'purchase_return_item_variants',
        'purchase_return_items', 'purchase_returns', 'purchases', 'sale_item_variants', 'sale_items',
        'sale_return_item_variants', 'sale_return_items', 'sale_returns', 'sales', 'vendor_branches',
        'vendor_businesses', 'vendors',
    ];

    public function handle(): int
    {
        if (app()->environment('local')) {
            $this->error('Refusing to run against a local environment — this command is for production use only.');

            return self::FAILURE;
        }

        $this->warn('This will permanently delete all rows from '.count($this->tablesToClear).' tables.');
        $this->line('Kept: merchants, users, roles/permissions, businesses, branches, products, categories, brands, countries, cities.');

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tablesToClear as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->truncate();
            $this->line("Cleared: {$table}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Done.');

        return self::SUCCESS;
    }
}
