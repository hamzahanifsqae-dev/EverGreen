<?php

namespace Tests\Feature\ColdStorage;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptAllocation;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\ColdStorage\CustomerStorageOverviewService;
use App\Services\ColdStorage\DispatchService;
use App\Services\ColdStorage\ReceiptService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerStorageOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.console' => true]);
        $this->installSchema();
    }

    public function test_customer_overview_summarises_received_returned_and_on_hand_stock(): void
    {
        $world = $this->bootstrap();

        $receipt = ColdStorageReceipt::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'receipt_no' => 'GR-VIEW-1',
            'received_on' => now()->subDays(3)->toDateString(),
            'status' => 'draft',
        ]);

        $item = ColdStorageReceiptItem::query()->create([
            'receipt_id' => $receipt->id,
            'product_id' => $world['product']->id,
            'lot_number' => 'LOT-VIEW-1',
            'package_count' => 40,
            'net_weight' => 400,
            'weight_unit' => 'kilogram',
        ]);

        ColdStorageReceiptAllocation::query()->create([
            'receipt_item_id' => $item->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_count' => 40,
            'net_weight' => 400,
        ]);

        app(ReceiptService::class)->post($receipt, null);

        $dispatch = ColdStorageDispatch::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'dispatch_no' => 'GD-VIEW-1',
            'dispatched_on' => now()->subDay()->toDateString(),
            'status' => 'draft',
            'recipient_name' => 'Driver',
        ]);

        ColdStorageDispatchLine::query()->create([
            'dispatch_id' => $dispatch->id,
            'receipt_item_id' => $item->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_count' => 10,
            'net_weight' => 100,
        ]);

        app(DispatchService::class)->post($dispatch, null);

        $overview = app(CustomerStorageOverviewService::class)->forCustomer($world['customer']);

        $this->assertSame(40.0, $overview['packages_received']);
        $this->assertSame(10.0, $overview['packages_returned']);
        $this->assertSame(30.0, $overview['packages_on_hand']);
        $this->assertSame(1, $overview['receipts_posted']);
        $this->assertSame(1, $overview['returns_posted']);
        $this->assertCount(1, $overview['receipts']);
        $this->assertCount(1, $overview['returns']);
        $this->assertSame('LOT-VIEW-1', $overview['stock_on_hand'][0]['lot_number']);
    }

    /**
     * @return array{merchant: Merchant, business: Business, branch: Branch, customer: Customer, product: Product, chamber: ColdStorageChamber, location: ColdStorageLocation}
     */
    private function bootstrap(): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Cold Store',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret-password',
            'address_line_1' => 'Warehouse road',
            'city' => 'Lahore',
            'status' => Merchant::STATUS_VERIFIED,
            'is_active' => true,
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
            'name' => 'Goods owner',
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
            'capacity_quantity' => 1000,
            'capacity_unit' => 'bag',
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
