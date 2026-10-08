<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\City;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageRateCard;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptAllocation;
use App\Models\ColdStorageReceiptItem;
use App\Models\ColdStorageReservation;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\DispatchService;
use App\Services\ColdStorage\ReceiptService;
use App\Services\ColdStorage\ReservationService;
use App\Services\ColdStorage\TemperatureService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Builds a ready-to-demo cold storage journey for the EverGreen merchant.
 *
 * Run: php artisan db:seed --class=ColdStorageDemoFlowSeeder
 */
class ColdStorageDemoFlowSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()->where('email', 'info@evergreen.com')->first();

        if (! $merchant) {
            $this->command?->error('Merchant info@evergreen.com not found. Seed merchants first.');

            return;
        }

        $business = Business::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'EverGreen Cold Storage'],
            ['id' => (string) Str::uuid(), 'status' => true],
        );

        $branch = Branch::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'business_id' => $business->id, 'name' => 'Main Cold Branch'],
            ['id' => (string) Str::uuid(), 'status' => Branch::STATUS_VERIFIED, 'is_active' => true],
        );

        $pakistan = Country::query()->where('code', 'PK')->first();
        $city = City::query()->where('name', 'Lahore')->first()
            ?? City::query()->where('name', 'Karachi')->first();

        if (! $pakistan || ! $city) {
            $this->command?->error('Country/City missing. Seed CountriesSeeder and CitiesSeeder first.');

            return;
        }

        $customer = Customer::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Ahmed Traders'],
            [
                'id' => (string) Str::uuid(),
                'email' => 'ahmed.traders@example.com',
                'phone' => '03001234567',
                'country_id' => $pakistan->id,
                'city_id' => $city->id,
                'postal_code' => '54000',
                'address' => 'Demo Street, Lahore',
                'reference' => 'Cold storage demo',
            ],
        );

        $product = Product::query()->where('merchant_id', $merchant->id)->where('sku', 'CS-POTATO')->first()
            ?? Product::query()->firstOrCreate(
                ['merchant_id' => $merchant->id, 'sku' => 'CS-DEMO-POTATO'],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'Demo Potatoes',
                    'description' => 'Demo cold storage product',
                    'type' => 'stock',
                    'unit' => 'bags',
                    'purchase_price' => 0,
                    'selling_price' => 0,
                    'track_inventory' => false,
                    'is_active' => true,
                ],
            );

        $chamber = ColdStorageChamber::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Demo Chamber A'],
            [
                'id' => (string) Str::uuid(),
                'merchant_id' => $merchant->id,
                'business_id' => $business->id,
                'capacity_quantity' => 1000,
                'capacity_unit' => 'bag',
                'min_temperature' => -5,
                'max_temperature' => 5,
                'temperature_unit' => 'C',
                'is_active' => true,
            ],
        );

        $location = ColdStorageLocation::query()->firstOrCreate(
            ['chamber_id' => $chamber->id, 'name' => 'Demo Rack A'],
            ['id' => (string) Str::uuid(), 'is_active' => true],
        );

        ColdStorageRateCard::query()->firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'charge_basis' => 'bag',
                'charge_period' => 'daily',
                'effective_from' => now()->subMonths(2)->toDateString(),
            ],
            [
                'id' => (string) Str::uuid(),
                'rate' => 10,
                'minimum_charge' => 0,
                'bill_arrival_day' => true,
                'bill_departure_day' => false,
                'rounding_mode' => 'nearest',
                'season_length_days' => 90,
            ],
        );

        $marker = 'DEMO-FLOW-GR';

        if (ColdStorageReceipt::query()->where('merchant_id', $merchant->id)->where('receipt_no', 'like', $marker.'%')->exists()) {
            $this->command?->warn('Demo flow receipts already exist. Skipping recreate.');

            return;
        }

        $receipt = ColdStorageReceipt::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'receipt_no' => $marker.'-'.now()->format('Ymd'),
            'received_on' => now()->subDays(4)->toDateString(),
            'vehicle_number' => 'LES-DEMO-01',
            'notes' => 'Demo goods receipt for EverGreen walkthrough',
            'status' => 'draft',
        ]);

        $item = ColdStorageReceiptItem::query()->create([
            'receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'lot_number' => 'LOT-DEMO-1',
            'package_count' => 40,
            'net_weight' => 400,
            'weight_unit' => 'kilogram',
        ]);

        ColdStorageReceiptAllocation::query()->create([
            'receipt_item_id' => $item->id,
            'chamber_id' => $chamber->id,
            'location_id' => $location->id,
            'package_count' => 40,
            'net_weight' => 400,
        ]);

        app(ReceiptService::class)->post($receipt, null);

        $dispatch = ColdStorageDispatch::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'dispatch_no' => 'DEMO-FLOW-GD-'.now()->format('Ymd'),
            'dispatched_on' => now()->subDay()->toDateString(),
            'recipient_name' => 'Demo Driver',
            'vehicle_number' => 'LES-OUT-01',
            'status' => 'draft',
        ]);

        ColdStorageDispatchLine::query()->create([
            'dispatch_id' => $dispatch->id,
            'receipt_item_id' => $item->id,
            'chamber_id' => $chamber->id,
            'location_id' => $location->id,
            'package_count' => 10,
            'net_weight' => 100,
        ]);

        app(DispatchService::class)->post($dispatch, null);

        app(TemperatureService::class)->record($chamber, 8, now()->subHours(2), null, 'manual', null, 'Demo out-of-range reading');

        $bill = app(BillingService::class)->createDraftFromStock([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'period_start' => now()->subDays(4)->toDateString(),
            'period_end' => now()->toDateString(),
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'notes' => 'Demo storage bill',
            'bill_no' => 'DEMO-FLOW-SB-'.now()->format('Ymd'),
        ], null);

        try {
            app(BillingService::class)->post($bill, null);
        } catch (\Throwable $exception) {
            $this->command?->warn('Demo bill left as draft: '.$exception->getMessage());
        }

        $reservation = ColdStorageReservation::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'chamber_id' => $chamber->id,
            'reservation_no' => 'DEMO-FLOW-RS-'.now()->format('Ymd'),
            'reserved_from' => now()->addDays(2)->toDateString(),
            'reserved_until' => now()->addDays(10)->toDateString(),
            'expected_packages' => 20,
            'status' => 'draft',
            'notes' => 'Demo booking for next week',
        ]);

        app(ReservationService::class)->confirm($reservation, null);

        $this->command?->info('Cold storage demo flow ready.');
        $this->command?->line('Customer: Ahmed Traders');
        $this->command?->line('Chamber: Demo Chamber A');
        $this->command?->line('Receipt / Dispatch / Bill / Reservation / Temp alert created.');
        $this->command?->line('Login: info@evergreen.com / Evergreen@123');
    }
}
