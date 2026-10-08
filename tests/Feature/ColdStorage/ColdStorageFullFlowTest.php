<?php

namespace Tests\Feature\ColdStorage;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ColdStorageAdjustment;
use App\Models\ColdStorageAdjustmentLine;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageRateCard;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptAllocation;
use App\Models\ColdStorageReceiptItem;
use App\Models\ColdStorageReservation;
use App\Models\ColdStorageTransfer;
use App\Models\ColdStorageTransferLine;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ColdStorage\ActionAlertService;
use App\Services\ColdStorage\AdjustmentService;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\DispatchService;
use App\Services\ColdStorage\OccupancyService;
use App\Services\ColdStorage\ReceiptService;
use App\Services\ColdStorage\ReportService;
use App\Services\ColdStorage\ReservationService;
use App\Services\ColdStorage\TemperatureService;
use App\Services\ColdStorage\TransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end cold storage lifecycle: setup → receive → transfer → temp →
 * dispatch → damage → bill → pay → reserve → reports/alerts.
 */
class ColdStorageFullFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.console' => true]);

        $this->installSchema();
    }

    public function test_full_cold_storage_lifecycle_works_end_to_end(): void
    {
        $world = $this->bootstrapFacility();

        // 1) Goods receipt
        $receipt = $this->makeReceipt($world, packages: 20, weight: 200, on: now()->subDays(5)->toDateString());
        app(ReceiptService::class)->post($receipt, null);
        $item = $receipt->items()->first();
        $this->assertSame('posted', $receipt->refresh()->status);
        $this->assertSame(20.0, $this->lotPackages($item->id));

        // 2) Internal transfer (same chamber, new rack)
        $binB = ColdStorageLocation::query()->create([
            'chamber_id' => $world['chamber']->id,
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'name' => 'Rack B',
        ]);
        $transfer = ColdStorageTransfer::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'transfer_no' => 'GT-FLOW-1',
            'transferred_on' => now()->subDays(4)->toDateString(),
            'status' => 'draft',
        ]);
        ColdStorageTransferLine::query()->create([
            'transfer_id' => $transfer->id,
            'receipt_item_id' => $item->id,
            'from_chamber_id' => $world['chamber']->id,
            'from_location_id' => $world['location']->id,
            'to_chamber_id' => $world['chamber']->id,
            'to_location_id' => $binB->id,
            'package_count' => 5,
            'net_weight' => 50,
        ]);
        app(TransferService::class)->post($transfer, null);
        $this->assertSame(15.0, $this->lotPackages($item->id, $world['location']->id));
        $this->assertSame(5.0, $this->lotPackages($item->id, $binB->id));

        // 3) Temperature exception + acknowledge
        $world['chamber']->update(['min_temperature' => -5, 'max_temperature' => 5]);
        $reading = app(TemperatureService::class)->record($world['chamber'], 12, now()->subDays(3), null);
        $this->assertTrue($reading->is_out_of_range);
        app(ActionAlertService::class)->acknowledgeTemperature($reading, null);
        $this->assertNotNull($reading->refresh()->acknowledged_at);

        // 4) Partial dispatch
        $dispatch = ColdStorageDispatch::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'dispatch_no' => 'GD-FLOW-1',
            'dispatched_on' => now()->subDays(2)->toDateString(),
            'recipient_name' => 'Driver Ali',
            'status' => 'draft',
        ]);
        ColdStorageDispatchLine::query()->create([
            'dispatch_id' => $dispatch->id,
            'receipt_item_id' => $item->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_count' => 6,
            'net_weight' => 60,
        ]);
        app(DispatchService::class)->post($dispatch, null);
        $this->assertSame(14.0, $this->lotPackages($item->id));

        // 5) Damage adjustment
        $adjustment = ColdStorageAdjustment::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'adjustment_no' => 'GA-FLOW-1',
            'kind' => 'damage',
            'adjusted_on' => now()->subDay()->toDateString(),
            'reason' => 'Torn packaging during transfer',
            'status' => 'draft',
        ]);
        ColdStorageAdjustmentLine::query()->create([
            'adjustment_id' => $adjustment->id,
            'receipt_item_id' => $item->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $binB->id,
            'package_delta' => -1,
            'weight_delta' => -10,
        ]);
        app(AdjustmentService::class)->post($adjustment, null);
        $this->assertSame(13.0, $this->lotPackages($item->id));

        // 6) Rate card + storage bill + payment
        ColdStorageRateCard::query()->create([
            'merchant_id' => $world['merchant']->id,
            'customer_id' => $world['customer']->id,
            'branch_id' => $world['branch']->id,
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'rate' => 10,
            'effective_from' => now()->subMonth()->toDateString(),
            'minimum_charge' => 0,
            'bill_arrival_day' => true,
            'bill_departure_day' => false,
            'rounding_mode' => 'nearest',
            'season_length_days' => 90,
        ]);

        $bill = app(BillingService::class)->createDraftFromStock([
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'period_start' => now()->subDays(5)->toDateString(),
            'period_end' => now()->toDateString(),
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
        ], null);

        $preview = app(BillingService::class)->preview($bill);
        $this->assertNotEmpty($preview['lines']);
        $this->assertGreaterThan(0, $preview['total']);

        $posted = app(BillingService::class)->post($bill, null);
        $this->assertSame('posted', $posted->status);
        $this->assertGreaterThan(0, (float) $posted->due_amount);

        app(BillingService::class)->recordPayment(
            $posted,
            min(100, (float) $posted->due_amount),
            now()->toDateString(),
            'cash_in_hand',
            null,
        );
        $this->assertGreaterThan(0, (float) $posted->refresh()->paid_amount);

        // 7) Reservation booking
        $reservation = ColdStorageReservation::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'chamber_id' => $world['chamber']->id,
            'reservation_no' => 'RS-FLOW-1',
            'reserved_from' => now()->addDays(2)->toDateString(),
            'reserved_until' => now()->addDays(9)->toDateString(),
            'expected_packages' => 8,
            'status' => 'draft',
        ]);
        app(ReservationService::class)->confirm($reservation, null);
        $this->assertSame('confirmed', $reservation->refresh()->status);

        // 8) Reports / occupancy / ageing
        $filters = [
            'merchant_id' => $world['merchant']->id,
            'branch_id' => $world['branch']->id,
        ];
        $reports = app(ReportService::class);
        $this->assertNotEmpty($reports->stockStatement($filters));
        $this->assertNotEmpty($reports->capacityHeatmap($filters));
        $this->assertNotEmpty($reports->stockAgeing($filters));

        $occupancy = app(OccupancyService::class)->forChamber($world['chamber']);
        $this->assertGreaterThan(0, $occupancy['occupied']);

        // Cold storage must never create classic sales stock
        $this->assertSame(0, Sale::query()->count());
        $this->assertGreaterThan(0, ColdStorageMovement::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function bootstrapFacility(): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'EverGreen Flow Test',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret-password',
            'address_line_1' => 'Warehouse road',
            'city' => 'Lahore',
            'status' => Merchant::STATUS_VERIFIED,
            'is_active' => true,
            'cash_in_hand' => 0,
            'cash_in_bank' => 0,
        ]);
        $business = Business::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Cold business',
            'status' => true,
        ]);
        $branch = Branch::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'name' => 'Main cold branch',
            'status' => Branch::STATUS_VERIFIED,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Demo Depositor',
            'email' => Str::uuid().'@example.com',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'name' => 'Potatoes',
            'sku' => 'POT-'.Str::upper(Str::random(4)),
            'purchase_price' => 0,
            'selling_price' => 0,
            'track_inventory' => true,
            'is_active' => true,
        ]);
        $chamber = ColdStorageChamber::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Chamber A',
            'capacity_quantity' => 500,
            'capacity_unit' => 'bag',
            'min_temperature' => -5,
            'max_temperature' => 5,
            'is_active' => true,
        ]);
        $location = ColdStorageLocation::query()->create([
            'chamber_id' => $chamber->id,
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Rack A',
        ]);

        return compact('merchant', 'business', 'branch', 'customer', 'product', 'chamber', 'location');
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function makeReceipt(array $world, float $packages, float $weight, string $on): ColdStorageReceipt
    {
        $receipt = ColdStorageReceipt::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'receipt_no' => 'GR-FLOW-'.Str::upper(Str::random(4)),
            'received_on' => $on,
            'vehicle_number' => 'LEA-100',
            'status' => 'draft',
        ]);
        $item = ColdStorageReceiptItem::query()->create([
            'receipt_id' => $receipt->id,
            'product_id' => $world['product']->id,
            'lot_number' => 'LOT-FLOW-1',
            'package_count' => $packages,
            'net_weight' => $weight,
            'weight_unit' => 'kilogram',
        ]);
        ColdStorageReceiptAllocation::query()->create([
            'receipt_item_id' => $item->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_count' => $packages,
            'net_weight' => $weight,
        ]);

        return $receipt;
    }

    private function lotPackages(string $receiptItemId, ?string $locationId = null): float
    {
        $query = ColdStorageMovement::query()->where('receipt_item_id', $receiptItemId);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return round((float) $query->sum('package_delta'), 3);
    }

    private function installSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('verified');
            $table->uuid('merchant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('city')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('cash_in_hand', 18, 2)->nullable();
            $table->decimal('cash_in_bank', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->string('postal_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->uuid('business_id');
            $table->string('name');
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('postal_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->uuid('business_id')->nullable();
            $table->string('name');
            $table->string('sku');
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        Schema::create('business_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('user_id');
            $table->timestamps();
        });

        Schema::create('branch_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('user_id');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->string('paymentable_type');
            $table->uuid('paymentable_id');
            $table->string('party_type');
            $table->uuid('party_id');
            $table->string('direction');
            $table->string('entry_type');
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('user');
            $table->string('event');
            $table->uuidMorphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_10_05_140000_create_cold_storage_tables.php');
        $migration->up();

        $insightsMigration = require database_path('migrations/2026_10_07_120000_add_cold_storage_insights_and_reservations.php');
        $insightsMigration->up();

        $alertEmailMigration = require database_path('migrations/2026_10_07_130000_create_cold_storage_alert_email_settings_table.php');
        $alertEmailMigration->up();
    }
}
