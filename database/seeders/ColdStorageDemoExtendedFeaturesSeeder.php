<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\DispatchService;
use Illuminate\Database\Seeder;

/**
 * Extends the EverGreen demo flow with the newer cold storage features:
 * dispatch-scoped billing ("Bill this withdrawal"), customer advances and
 * allocation, and legacy customer references.
 *
 * Run: php artisan db:seed --class=ColdStorageDemoExtendedFeaturesSeeder
 * Requires: ColdStorageDemoFlowSeeder to have already run.
 */
class ColdStorageDemoExtendedFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()->where('email', 'info@evergreen.com')->first();

        if (! $merchant) {
            $this->command?->error('Merchant info@evergreen.com not found. Seed merchants first.');

            return;
        }

        $business = Business::query()->where('merchant_id', $merchant->id)->where('name', 'EverGreen Cold Storage')->first();
        $branch = Branch::query()->where('merchant_id', $merchant->id)->where('name', 'Main Cold Branch')->first();
        $customer = Customer::query()->where('merchant_id', $merchant->id)->where('name', 'Ahmed Traders')->first();
        $chamber = ColdStorageChamber::query()->where('branch_id', $branch?->id)->where('name', 'Demo Chamber A')->first();
        $location = ColdStorageLocation::query()->where('chamber_id', $chamber?->id)->where('name', 'Demo Rack A')->first();
        $item = ColdStorageReceiptItem::query()->where('lot_number', 'LOT-DEMO-1')->first();

        if (! $business || ! $branch || ! $customer || ! $chamber || ! $item) {
            $this->command?->error('Base demo flow not found. Run ColdStorageDemoFlowSeeder first.');

            return;
        }

        if (ColdStorageDispatch::query()->where('dispatch_no', 'like', 'DEMO-EXT-GD%')->exists()) {
            $this->command?->warn('Extended demo flow already exists. Skipping recreate.');

            return;
        }

        // Legacy customer references (Customers / Customer Index feature).
        $customer->update(['account_code' => 'ACC-AHMED-01']);
        $customer->legacyReferences()->firstOrCreate([
            'merchant_id' => $merchant->id,
            'season' => '2025-26',
            'book' => '3',
            'page' => '12',
        ], [
            'note' => 'Carried over from the paper register.',
        ]);

        // A second withdrawal, billed through "Bill this withdrawal" (dispatch-scoped bill).
        // Dated a day after the base flow's bill period so there is genuinely new,
        // not-yet-billed entitlement for "Bill this withdrawal" to charge.
        $dispatch = ColdStorageDispatch::query()->create([
            'merchant_id' => $merchant->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'dispatch_no' => 'DEMO-EXT-GD-'.now()->format('Ymd'),
            'legacy_reference' => 'REG-PAGE-12-ROW-4',
            'dispatched_on' => now()->addDay()->toDateString(),
            'recipient_name' => 'Demo Driver 2',
            'vehicle_number' => 'LES-OUT-02',
            'status' => 'draft',
        ]);

        ColdStorageDispatchLine::query()->create([
            'dispatch_id' => $dispatch->id,
            'receipt_item_id' => $item->id,
            'chamber_id' => $chamber->id,
            'location_id' => $location?->id,
            'package_count' => 10,
            'net_weight' => 100,
        ]);

        app(DispatchService::class)->post($dispatch, null);

        $billing = app(BillingService::class);
        $withdrawalBill = $billing->createDraftFromDispatch($dispatch, null);

        try {
            $withdrawalBill = $billing->post($withdrawalBill, null);
            $this->command?->line('Dispatch-scoped bill posted: '.$withdrawalBill->bill_no);
        } catch (\Throwable $exception) {
            $this->command?->warn('Dispatch-scoped bill left as draft: '.$exception->getMessage());
        }

        // An advance payment, partially allocated to the posted withdrawal bill.
        $advance = $billing->recordAdvancePayment($customer, 1000, now()->toDateString(), 'cash_in_hand', null);

        if ($withdrawalBill->isPosted() && (float) $withdrawalBill->due_amount > 0) {
            $allocation = min(500.0, (float) $withdrawalBill->due_amount);
            $billing->allocateAdvanceToBill($advance, $withdrawalBill, $allocation, now()->toDateString(), null);
            $this->command?->line('Advance of 1,000 recorded; '.$allocation.' allocated to '.$withdrawalBill->bill_no.'.');
        } else {
            $this->command?->line('Advance of 1,000 recorded (left unallocated; withdrawal bill not posted).');
        }

        $this->command?->info('Cold storage extended demo features ready.');
        $this->command?->line('Customer ledger, stock register, and rent collection register now have data to show.');
    }
}
