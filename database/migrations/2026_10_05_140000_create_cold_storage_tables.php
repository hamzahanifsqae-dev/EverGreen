<?php

use App\Models\Merchant;
use App\Models\Permission;
use App\Models\PermissionModule;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_storage_chambers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('capacity_quantity', 14, 3);
            $table->string('capacity_unit');
            $table->decimal('min_temperature', 8, 2)->nullable();
            $table->decimal('max_temperature', 8, 2)->nullable();
            $table->string('temperature_unit')->default('C');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('cold_storage_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->cascadeOnDelete();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('capacity_quantity', 14, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cold_storage_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no');
            $table->date('received_on');
            $table->string('vehicle_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'receipt_no']);
        });

        Schema::create('cold_storage_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('cold_storage_receipts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('lot_number');
            $table->decimal('package_count', 14, 3);
            $table->decimal('net_weight', 14, 3);
            $table->string('weight_unit');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cold_storage_receipt_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->cascadeOnDelete();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->decimal('package_count', 14, 3);
            $table->decimal('net_weight', 14, 3);
            $table->timestamps();
        });

        Schema::create('cold_storage_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUuid('receipt_id')->nullable()->constrained('cold_storage_receipts')->nullOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->string('lot_number');
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->string('movement_type');
            $table->decimal('package_delta', 14, 3);
            $table->decimal('weight_delta', 14, 3);
            $table->string('weight_unit');
            $table->decimal('capacity_delta', 14, 3);
            $table->string('capacity_unit');
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->uuid('reverses_movement_id')->nullable();
            $table->text('reason')->nullable();
            $table->date('occurred_on');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['merchant_id', 'branch_id', 'customer_id', 'receipt_item_id', 'chamber_id'],
                'cold_storage_movements_balance_index'
            );
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('cold_storage_movements', function (Blueprint $table) {
            $table->foreign('reverses_movement_id')
                ->references('id')
                ->on('cold_storage_movements')
                ->nullOnDelete();
        });

        Schema::create('cold_storage_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('dispatch_no');
            $table->date('dispatched_on');
            $table->string('recipient_name');
            $table->string('vehicle_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'dispatch_no']);
        });

        Schema::create('cold_storage_dispatch_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispatch_id')->constrained('cold_storage_dispatches')->cascadeOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->decimal('package_count', 14, 3);
            $table->decimal('net_weight', 14, 3);
            $table->timestamps();
        });

        Schema::create('cold_storage_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('transfer_no');
            $table->date('transferred_on');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'transfer_no']);
        });

        Schema::create('cold_storage_transfer_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('cold_storage_transfers')->cascadeOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->foreignUuid('from_chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('from_location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->foreignUuid('to_chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->decimal('package_count', 14, 3);
            $table->decimal('net_weight', 14, 3);
            $table->timestamps();
        });

        Schema::create('cold_storage_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_no');
            $table->string('kind');
            $table->date('adjusted_on');
            $table->text('reason');
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'adjustment_no']);
        });

        Schema::create('cold_storage_adjustment_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('adjustment_id')->constrained('cold_storage_adjustments')->cascadeOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('cold_storage_locations')->nullOnDelete();
            $table->decimal('package_delta', 14, 3);
            $table->decimal('weight_delta', 14, 3);
            $table->timestamps();
        });

        Schema::create('cold_storage_rate_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('charge_basis');
            $table->string('charge_period');
            $table->decimal('rate', 14, 4);
            $table->string('currency')->default('PKR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('minimum_charge', 14, 2)->default(0);
            $table->boolean('bill_arrival_day')->default(true);
            $table->boolean('bill_departure_day')->default(false);
            $table->string('rounding_mode')->default('nearest');
            $table->unsignedInteger('season_length_days')->default(90);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'charge_basis', 'effective_from'], 'cold_storage_rate_cards_lookup_index');
        });

        Schema::create('cold_storage_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('bill_no');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('charge_basis');
            $table->string('charge_period');
            $table->string('status')->default('draft');
            $table->decimal('storage_total', 14, 2)->default(0);
            $table->decimal('service_total', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'bill_no']);
        });

        Schema::create('cold_storage_bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_id')->constrained('cold_storage_bills')->cascadeOnDelete();
            $table->foreignUuid('receipt_item_id')->constrained('cold_storage_receipt_items')->restrictOnDelete();
            $table->foreignUuid('rate_card_id')->nullable()->constrained('cold_storage_rate_cards')->nullOnDelete();
            $table->string('lot_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('charge_basis');
            $table->string('charge_period');
            $table->decimal('quantity_days', 14, 3);
            $table->unsignedInteger('billable_days');
            $table->decimal('rate', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->boolean('bill_arrival_day');
            $table->boolean('bill_departure_day');
            $table->string('rounding_mode');
            $table->unsignedInteger('season_length_days');
            $table->decimal('minimum_charge', 14, 2)->default(0);
            $table->text('calculation_note')->nullable();
            $table->timestamps();

            $table->index(['receipt_item_id', 'period_start', 'period_end'], 'cold_storage_bill_lines_period_index');
        });

        Schema::create('cold_storage_bill_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_id')->constrained('cold_storage_bills')->cascadeOnDelete();
            $table->string('name');
            $table->string('basis');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->decimal('rate', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('cold_storage_temperature_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('chamber_id')->constrained('cold_storage_chambers')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('temperature', 8, 2);
            $table->string('temperature_unit')->default('C');
            $table->decimal('min_temperature', 8, 2)->nullable();
            $table->decimal('max_temperature', 8, 2)->nullable();
            $table->boolean('is_out_of_range')->default(false);
            $table->string('source')->default('manual');
            $table->string('sensor_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chamber_id', 'recorded_at']);
        });

        $this->seedAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_storage_temperature_readings');
        Schema::dropIfExists('cold_storage_bill_services');
        Schema::dropIfExists('cold_storage_bill_lines');
        Schema::dropIfExists('cold_storage_bills');
        Schema::dropIfExists('cold_storage_rate_cards');
        Schema::dropIfExists('cold_storage_adjustment_lines');
        Schema::dropIfExists('cold_storage_adjustments');
        Schema::dropIfExists('cold_storage_transfer_lines');
        Schema::dropIfExists('cold_storage_transfers');
        Schema::dropIfExists('cold_storage_dispatch_lines');
        Schema::dropIfExists('cold_storage_dispatches');
        Schema::dropIfExists('cold_storage_movements');
        Schema::dropIfExists('cold_storage_receipt_allocations');
        Schema::dropIfExists('cold_storage_receipt_items');
        Schema::dropIfExists('cold_storage_receipts');
        Schema::dropIfExists('cold_storage_locations');
        Schema::dropIfExists('cold_storage_chambers');
    }

    private function seedAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_modules') || ! Schema::hasTable('merchants')) {
            return;
        }

        $permissionNames = [
            'cold_storage.view',
            'cold_storage.create',
            'cold_storage.update',
            'cold_storage.delete',
        ];

        foreach (['merchant', 'staff'] as $guard) {
            foreach ($permissionNames as $name) {
                Permission::query()->firstOrCreate([
                    'name' => $name,
                    'guard_name' => $guard,
                ]);
            }
        }

        $module = PermissionModule::query()->updateOrCreate(
            ['module' => 'cold_storage'],
            ['label' => 'Cold Storage'],
        );

        $now = now();

        Merchant::query()->pluck('id')->each(function (string $merchantId) use ($module, $now): void {
            $exists = DB::table('merchant_permission_modules')
                ->where('merchant_id', $merchantId)
                ->where('permission_module_id', $module->id)
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('merchant_permission_modules')->insert([
                'id' => (string) Str::uuid(),
                'merchant_id' => $merchantId,
                'permission_module_id' => $module->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $merchantPermissions = Permission::query()
            ->where('guard_name', 'merchant')
            ->whereIn('name', $permissionNames)
            ->get();

        Role::query()
            ->where('guard_name', 'merchant')
            ->where('name', 'Admin')
            ->get()
            ->each(function (Role $role) use ($merchantPermissions): void {
                $role->givePermissionTo($merchantPermissions);
            });
    }
};
