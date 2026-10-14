<?php

namespace Tests\Feature\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Mail\ColdStorageActionAlertsMailable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\ColdStorageAdjustment;
use App\Models\ColdStorageAdjustmentLine;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageBillLine;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageRateCard;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptAllocation;
use App\Models\ColdStorageReceiptItem;
use App\Models\ColdStorageTransfer;
use App\Models\ColdStorageTransferLine;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\ColdStorage\ActionAlertMailService;
use App\Services\ColdStorage\ActionAlertService;
use App\Services\ColdStorage\AdjustmentService;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\DispatchService;
use App\Services\ColdStorage\OccupancyService;
use App\Services\ColdStorage\ReceiptService;
use App\Services\ColdStorage\ReportService;
use App\Services\ColdStorage\StockRegisterService;
use App\Services\ColdStorage\TemperatureService;
use App\Services\ColdStorage\TransferService;
use App\Support\ColdStorageAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ColdStorageOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.console' => true]);

        $this->installSchema();
    }

    public function test_partial_dispatch_leaves_the_remaining_customer_stock(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100);

        $this->dispatchGoods($world, packages: 4, weight: 40);

        $balance = $this->balance($world);

        $this->assertSame(6.0, $balance['packages']);
        $this->assertSame(60.0, $balance['weight']);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_dispatch_above_available_stock_is_rejected(): void
    {
        $world = $this->storeGoods();

        $this->expectException(ColdStorageException::class);

        $this->dispatchGoods($world, packages: 11, weight: 10);
    }

    public function test_a_second_withdrawal_cannot_take_stock_already_dispatched(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100);
        $this->dispatchGoods($world, packages: 6, weight: 60);

        try {
            $this->dispatchGoods($world, packages: 6, weight: 40);
            $this->fail('The second withdrawal should have been rejected.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('available customer stock', $exception->getMessage());
        }

        $this->assertSame(4.0, $this->balance($world)['packages']);
    }

    public function test_stock_cannot_be_withdrawn_for_another_customer(): void
    {
        $world = $this->storeGoods();
        $other = Customer::query()->create([
            'merchant_id' => $world['merchant']->id,
            'name' => 'Other customer',
            'email' => Str::uuid().'@example.com',
        ]);

        $dispatch = $this->makeDispatch($world, $other, 1, 1);

        $this->expectException(ColdStorageException::class);
        $this->expectExceptionMessage('different customer');

        app(DispatchService::class)->post($dispatch, null);
    }

    public function test_a_chamber_from_another_branch_cannot_receive_goods(): void
    {
        $world = $this->world();
        $otherBranch = Branch::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'name' => 'Other branch',
            'status' => Branch::STATUS_VERIFIED,
            'is_active' => true,
        ]);
        $foreignChamber = $this->chamber($world, $otherBranch);

        $receipt = $this->makeReceipt($world, $foreignChamber, 5, 50);

        $this->expectException(ColdStorageException::class);
        $this->expectExceptionMessage('different business or branch');

        app(ReceiptService::class)->post($receipt, null);
    }

    public function test_transfers_move_stock_between_locations_without_changing_the_customer_total(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 80);
        $destination = ColdStorageLocation::query()->create([
            'chamber_id' => $world['chamber']->id,
            'name' => 'Bin B',
        ]);

        $transfer = ColdStorageTransfer::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'transfer_no' => 'GT-1',
            'transferred_on' => now()->toDateString(),
            'status' => 'draft',
        ]);
        ColdStorageTransferLine::query()->create([
            'transfer_id' => $transfer->id,
            'receipt_item_id' => $world['item']->id,
            'from_chamber_id' => $world['chamber']->id,
            'from_location_id' => $world['location']->id,
            'to_chamber_id' => $world['chamber']->id,
            'to_location_id' => $destination->id,
            'package_count' => 4,
            'net_weight' => 30,
        ]);

        app(TransferService::class)->post($transfer, null);

        $this->assertSame(6.0, $this->balance($world)['packages']);
        $this->assertSame(4.0, $this->balance($world, $destination->id)['packages']);
        $this->assertSame(10.0, round((float) ColdStorageMovement::query()->where('receipt_item_id', $world['item']->id)->sum('package_delta'), 3));
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_damage_requires_a_reason_and_reduces_stock(): void
    {
        $world = $this->storeGoods();
        $adjustment = ColdStorageAdjustment::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'adjustment_no' => 'GA-EMPTY',
            'kind' => 'damage',
            'adjusted_on' => now()->toDateString(),
            'reason' => '   ',
            'status' => 'draft',
        ]);

        try {
            app(AdjustmentService::class)->post($adjustment, null);
            $this->fail('Damage without a reason should be rejected.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('reason', $exception->getMessage());
        }

        $adjustment->update(['reason' => 'Torn bags found at intake']);
        ColdStorageAdjustmentLine::query()->create([
            'adjustment_id' => $adjustment->id,
            'receipt_item_id' => $world['item']->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_delta' => -2,
            'weight_delta' => -15,
        ]);

        app(AdjustmentService::class)->post($adjustment->refresh(), null);

        $this->assertSame(8.0, $this->balance($world)['packages']);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => ColdStorageAdjustment::class,
            'auditable_id' => $adjustment->id,
        ]);
    }

    public function test_cancelling_a_dispatch_reverses_the_stock_movement(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 50);
        $dispatch = $this->dispatchGoods($world, packages: 3, weight: 10);

        app(DispatchService::class)->cancel($dispatch, null, 'Wrong vehicle');

        $this->assertSame(10.0, $this->balance($world)['packages']);
        $this->assertSame('cancelled', $dispatch->refresh()->status);
        $this->assertSame(2, ColdStorageMovement::query()->where('reference_id', $dispatch->id)->count());
    }

    public function test_rate_changes_do_not_rewrite_posted_bills_and_same_day_rebills_are_allowed(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(2)->toDateString());
        $card = $this->rate($world, 5);

        $bill = $this->bill($world, now()->subDays(2)->toDateString(), now()->subDay()->toDateString());
        app(BillingService::class)->post($bill, null);

        $postedRate = (float) ColdStorageBillLine::query()->where('bill_id', $bill->id)->value('rate');
        $card->update(['rate' => 9]);

        $this->assertSame(5.0, $postedRate);
        $this->assertSame(5.0, (float) ColdStorageBillLine::query()->where('bill_id', $bill->id)->value('rate'));

        $this->dispatchGoods($world, packages: 5, weight: 50);

        $rebill = $this->bill($world, now()->subDays(2)->toDateString(), now()->toDateString(), 'SB-2');
        $postedRebill = app(BillingService::class)->post($rebill, null);

        $this->assertSame('posted', $postedRebill->status);
        $this->assertSame(5.0, (float) ColdStorageBillLine::query()->where('bill_id', $bill->id)->value('rate'));
        $this->assertSame(9.0, (float) ColdStorageBillLine::query()->where('bill_id', $rebill->id)->value('rate'));

        // The two already-billed days (10 bags each) are not charged again: only the
        // new day's remaining 5 bags (after the partial withdrawal) are entitled.
        $this->assertSame(5.0, (float) ColdStorageBillLine::query()->where('bill_id', $rebill->id)->value('quantity_days'));
        $this->assertSame(45.0, (float) $postedRebill->total_amount);
    }

    public function test_an_identical_rebill_with_no_new_entitlement_is_rejected(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(2)->toDateString());
        $this->rate($world, 5);

        $bill = $this->bill($world, now()->subDays(2)->toDateString(), now()->subDay()->toDateString());
        app(BillingService::class)->post($bill, null);

        $duplicate = $this->bill($world, now()->subDays(2)->toDateString(), now()->subDay()->toDateString(), 'SB-DUPLICATE');

        try {
            app(BillingService::class)->post($duplicate, null);
            $this->fail('A duplicate bill for already-billed days should have been rejected.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('no storage quantity', $exception->getMessage());
        }
    }

    public function test_cancelling_a_bill_releases_its_entitlement_for_a_future_rebill(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(2)->toDateString());
        $this->rate($world, 5);

        $bill = $this->bill($world, now()->subDays(2)->toDateString(), now()->subDay()->toDateString());
        $posted = app(BillingService::class)->post($bill, null);
        app(BillingService::class)->cancel($posted, null, 'Wrong rate applied');

        $rebill = $this->bill($world, now()->subDays(2)->toDateString(), now()->subDay()->toDateString(), 'SB-AFTER-CANCEL');
        $postedRebill = app(BillingService::class)->post($rebill, null);

        $this->assertSame('posted', $postedRebill->status);
        $this->assertSame(20.0, (float) ColdStorageBillLine::query()->where('bill_id', $rebill->id)->value('quantity_days'));
    }

    public function test_bill_this_withdrawal_scopes_billing_to_the_dispatched_lot_only(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(3)->toDateString());
        $this->rate($world, 4);
        $dispatch = $this->dispatchGoods($world, packages: 6, weight: 60);

        $bill = app(BillingService::class)->createDraftFromDispatch($dispatch, null);

        $this->assertSame($dispatch->id, $bill->dispatch_id);
        $this->assertSame($world['branch']->id, $bill->branch_id);

        $posted = app(BillingService::class)->post($bill, null);

        $this->assertSame('posted', $posted->status);
        $this->assertGreaterThan(0, (float) $posted->total_amount);
        $this->assertSame($world['item']->id, ColdStorageBillLine::query()->where('bill_id', $posted->id)->value('receipt_item_id'));
    }

    public function test_bill_this_withdrawal_charges_only_the_withdrawn_quantity_even_on_a_same_day_round_trip(): void
    {
        $today = now()->toDateString();
        $world = $this->storeGoods(packages: 50, weight: 500, on: $today);
        $this->rate($world, 10, billArrival: true, billDeparture: false);
        $dispatch = $this->dispatchGoods($world, packages: 20, weight: 200);

        $withdrawalBill = app(BillingService::class)->createDraftFromDispatch($dispatch, null);
        $preview = app(BillingService::class)->preview($withdrawalBill);

        // Only the 20 withdrawn bags are billed here, for the one day they sat
        // in the chamber — not the 30 that remain, even though withdrawal
        // happened the same day the lot arrived.
        $this->assertCount(1, $preview['lines']);
        $this->assertSame(20.0, (float) $preview['lines'][0]['quantity_days']);
        $this->assertSame(200.0, (float) $preview['lines'][0]['line_total']);

        app(BillingService::class)->post($withdrawalBill, null);

        // A later period bill covering the same day must only pick up the 30
        // bags still in the chamber: the entitlement already claimed by the
        // withdrawal bill is excluded, so nothing is double-billed.
        $periodBill = $this->bill($world, $today, $today, 'SB-PERIOD-1');
        $periodPreview = app(BillingService::class)->preview($periodBill);

        $this->assertCount(1, $periodPreview['lines']);
        $this->assertSame(30.0, (float) $periodPreview['lines'][0]['quantity_days']);
        $this->assertSame(300.0, (float) $periodPreview['lines'][0]['line_total']);
    }

    public function test_an_advance_allocated_to_a_bill_increases_cash_once_and_is_not_double_credited(): void
    {
        $world = $this->storeGoods(packages: 2, weight: 20, on: now()->toDateString());
        $this->rate($world, 10, billArrival: true);
        $bill = $this->bill($world, now()->toDateString(), now()->toDateString());
        $posted = app(BillingService::class)->post($bill, null);

        $billing = app(BillingService::class);
        $advance = $billing->recordAdvancePayment($world['customer'], 1000, now()->toDateString(), 'cash_in_hand', null);

        $merchantAfterAdvance = $world['merchant']->refresh();
        $this->assertSame(2000.0, (float) $merchantAfterAdvance->cash_in_hand);
        $this->assertSame(1000.0, (float) $advance->unallocated_amount);

        $billing->allocateAdvanceToBill($advance, $posted, 20, now()->toDateString(), null);

        $merchantAfterAllocation = $world['merchant']->refresh();
        $this->assertSame(2000.0, (float) $merchantAfterAllocation->cash_in_hand, 'Allocating an advance must not move cash again.');
        $this->assertSame(0.0, (float) $posted->refresh()->due_amount);
        $this->assertSame(980.0, (float) $advance->refresh()->unallocated_amount);
    }

    public function test_an_allocation_cannot_exceed_the_bills_outstanding_balance(): void
    {
        $world = $this->storeGoods(packages: 2, weight: 20, on: now()->toDateString());
        $this->rate($world, 10, billArrival: true);
        $bill = $this->bill($world, now()->toDateString(), now()->toDateString());
        $posted = app(BillingService::class)->post($bill, null);

        $billing = app(BillingService::class);
        $advance = $billing->recordAdvancePayment($world['customer'], 1000, now()->toDateString(), 'cash_in_hand', null);

        $this->expectException(ColdStorageException::class);
        $this->expectExceptionMessage("outstanding balance");

        $billing->allocateAdvanceToBill($advance, $posted, 500, now()->toDateString(), null);
    }

    public function test_a_backdated_withdrawal_before_the_receipt_date_is_rejected(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->toDateString());

        $dispatch = $this->makeDispatch($world, $world['customer'], 5, 50);
        $dispatch->update(['dispatched_on' => now()->subDays(5)->toDateString()]);

        try {
            app(DispatchService::class)->post($dispatch, null);
            $this->fail('A withdrawal dated before the receipt should have been rejected.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('available customer stock', $exception->getMessage());
        }
    }

    public function test_a_backdated_insert_cannot_create_a_negative_balance_between_existing_movements(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(10)->toDateString());
        $this->dispatchGoods($world, packages: 8, weight: 80);

        $backdated = $this->makeDispatch($world, $world['customer'], 5, 50);
        $backdated->update(['dispatched_on' => now()->subDays(5)->toDateString()]);

        try {
            app(DispatchService::class)->post($backdated, null);
            $this->fail('A backdated withdrawal that strands a later movement should have been rejected.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('available customer stock', $exception->getMessage());
        }

        $this->assertSame(2.0, $this->balance($world)['packages']);
    }

    public function test_billing_uses_remaining_quantity_and_can_exclude_the_arrival_day(): void
    {
        $receivedOn = now()->subDay()->toDateString();
        $world = $this->storeGoods(packages: 10, weight: 100, on: $receivedOn);
        $this->dispatchGoods($world, packages: 4, weight: 40);
        $card = $this->rate($world, 2, billArrival: false, billDeparture: false);

        $bill = $this->bill($world, $receivedOn, now()->toDateString());
        $preview = app(BillingService::class)->preview($bill);

        $this->assertCount(1, $preview['lines']);
        $this->assertSame(6.0, (float) $preview['lines'][0]['quantity_days']);
        $this->assertSame(12.0, (float) $preview['lines'][0]['line_total']);
        $this->assertFalse($card->bill_arrival_day);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_a_same_day_round_trip_still_owes_the_arrival_days_rent(): void
    {
        $today = now()->toDateString();
        $world = $this->storeGoods(packages: 10, weight: 100, on: $today);
        $this->dispatchGoods($world, packages: 4, weight: 40);
        $this->rate($world, 10, billArrival: true, billDeparture: false);

        $bill = $this->bill($world, $today, $today);
        $preview = app(BillingService::class)->preview($bill);

        // All 10 packages were present on the arrival day, even though 4 of them
        // left the same day: bill_arrival_day must still charge for the full
        // lot, not just the 6 that are left sitting in the chamber.
        $this->assertCount(1, $preview['lines']);
        $this->assertSame(10.0, (float) $preview['lines'][0]['quantity_days']);
        $this->assertSame(100.0, (float) $preview['lines'][0]['line_total']);
    }

    public function test_service_charges_and_payments_do_not_touch_product_stock(): void
    {
        $world = $this->storeGoods(packages: 2, weight: 20, on: now()->toDateString());
        $this->rate($world, 10, billArrival: true);
        $bill = $this->bill($world, now()->toDateString(), now()->toDateString());
        $bill->services()->create([
            'name' => 'Unloading',
            'basis' => 'flat',
            'quantity' => 1,
            'rate' => 150,
        ]);

        $posted = app(BillingService::class)->post($bill, null);
        app(BillingService::class)->recordPayment($posted, 50, now()->toDateString(), 'cash_in_hand', null);

        $this->assertSame(170.0, (float) $posted->refresh()->total_amount);
        $this->assertSame(120.0, (float) $posted->due_amount);
        $this->assertSame(1, $posted->payments()->count());
        $this->assertSame('cash_in_hand', $posted->payments()->value('method'));
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(2.0, $this->balance($world)['packages']);
    }

    public function test_storage_bill_payment_credits_cash_in_hand_or_bank(): void
    {
        $world = $this->storeGoods(packages: 2, weight: 20, on: now()->toDateString());
        $this->rate($world, 10, billArrival: true);
        $bill = $this->bill($world, now()->toDateString(), now()->toDateString());

        $posted = app(BillingService::class)->post($bill, null);
        $this->assertSame(20.0, (float) $posted->due_amount);

        $billing = app(BillingService::class);
        $billing->recordPayment($posted, 12, now()->toDateString(), 'cash_in_hand', null);
        $billing->recordPayment($posted->refresh(), 8, now()->toDateString(), 'cash_in_bank', null);

        $merchant = $world['merchant']->refresh();

        $this->assertSame(1012.0, (float) $merchant->cash_in_hand);
        $this->assertSame(2008.0, (float) $merchant->cash_in_bank);
        $this->assertSame(20.0, (float) $posted->refresh()->paid_amount);
        $this->assertSame(0.0, (float) $posted->due_amount);
        $this->assertSame(
            ['cash_in_hand', 'cash_in_bank'],
            $posted->payments()->orderBy('created_at')->pluck('method')->all(),
        );
    }

    public function test_temperature_readings_flag_values_outside_the_chamber_limits(): void
    {
        $world = $this->world();
        $chamber = $this->chamber($world);
        $chamber->update(['min_temperature' => -20, 'max_temperature' => -10]);

        $reading = app(TemperatureService::class)->record($chamber, -5, now(), null);

        $this->assertTrue($reading->is_out_of_range);
        $this->assertSame('-20.00', (string) $reading->min_temperature);
    }

    public function test_occupancy_uses_the_chamber_unit_and_staff_are_limited_to_assigned_branches(): void
    {
        $world = $this->storeGoods(packages: 4, weight: 40);
        $levels = app(OccupancyService::class)->forChamber($world['chamber']);

        $this->assertSame(4.0, $levels['occupied']);
        $this->assertSame(96.0, $levels['available']);
        $this->assertSame('bag', $levels['unit']);

        $staff = User::query()->create([
            'name' => 'Store clerk',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret-password',
            'merchant_id' => $world['merchant']->id,
            'status' => User::STATUS_VERIFIED,
            'is_active' => true,
        ]);
        $staff->businesses()->attach($world['business']->id, ['id' => (string) Str::uuid()]);
        $staff->branches()->attach($world['branch']->id, ['id' => (string) Str::uuid()]);

        $otherBranch = Branch::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'name' => 'Closed wing',
            'status' => Branch::STATUS_VERIFIED,
            'is_active' => true,
        ]);
        ColdStorageReceipt::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $otherBranch->id,
            'customer_id' => $world['customer']->id,
            'receipt_no' => 'GR-OTHER',
            'received_on' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $visible = ColdStorageAccess::scope(ColdStorageReceipt::query(), $staff)->pluck('receipt_no');

        $this->assertTrue($visible->contains($world['receipt']->receipt_no));
        $this->assertFalse($visible->contains('GR-OTHER'));
    }

    /**
     * @return array<string, mixed>
     */
    private function storeGoods(float $packages = 10, float $weight = 100, ?string $on = null): array
    {
        $world = $this->world();
        $chamber = $this->chamber($world);
        $location = ColdStorageLocation::query()->create([
            'chamber_id' => $chamber->id,
            'name' => 'Rack A',
        ]);
        $receipt = $this->makeReceipt($world, $chamber, $packages, $weight, $location, $on);
        app(ReceiptService::class)->post($receipt, null);

        return [
            ...$world,
            'chamber' => $chamber,
            'location' => $location,
            'receipt' => $receipt->refresh(),
            'item' => $receipt->items()->first(),
        ];
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function dispatchGoods(array $world, float $packages, float $weight): ColdStorageDispatch
    {
        $dispatch = $this->makeDispatch($world, $world['customer'], $packages, $weight);
        app(DispatchService::class)->post($dispatch, null);

        return $dispatch->refresh();
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function makeDispatch(array $world, Customer $customer, float $packages, float $weight): ColdStorageDispatch
    {
        $dispatch = ColdStorageDispatch::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $customer->id,
            'dispatch_no' => 'GD-'.Str::upper(Str::random(6)),
            'dispatched_on' => now()->toDateString(),
            'recipient_name' => 'Driver',
            'status' => 'draft',
        ]);
        ColdStorageDispatchLine::query()->create([
            'dispatch_id' => $dispatch->id,
            'receipt_item_id' => $world['item']->id,
            'chamber_id' => $world['chamber']->id,
            'location_id' => $world['location']->id,
            'package_count' => $packages,
            'net_weight' => $weight,
        ]);

        return $dispatch;
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function makeReceipt(array $world, ColdStorageChamber $chamber, float $packages, float $weight, ?ColdStorageLocation $location = null, ?string $on = null): ColdStorageReceipt
    {
        $receipt = ColdStorageReceipt::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'receipt_no' => 'GR-'.Str::upper(Str::random(6)),
            'received_on' => $on ?? now()->toDateString(),
            'vehicle_number' => 'LEA-100',
            'status' => 'draft',
        ]);
        $item = ColdStorageReceiptItem::query()->create([
            'receipt_id' => $receipt->id,
            'product_id' => $world['product']->id,
            'lot_number' => 'LOT-1',
            'package_count' => $packages,
            'net_weight' => $weight,
            'weight_unit' => 'kilogram',
        ]);
        ColdStorageReceiptAllocation::query()->create([
            'receipt_item_id' => $item->id,
            'chamber_id' => $chamber->id,
            'location_id' => $location?->id,
            'package_count' => $packages,
            'net_weight' => $weight,
        ]);

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function chamber(array $world, ?Branch $branch = null): ColdStorageChamber
    {
        $branch ??= $world['branch'];

        return ColdStorageChamber::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'name' => 'Chamber '.$branch->name,
            'capacity_quantity' => 100,
            'capacity_unit' => 'bag',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function rate(array $world, float $rate, bool $billArrival = true, bool $billDeparture = false): ColdStorageRateCard
    {
        return ColdStorageRateCard::query()->create([
            'merchant_id' => $world['merchant']->id,
            'customer_id' => $world['customer']->id,
            'branch_id' => $world['branch']->id,
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'rate' => $rate,
            'effective_from' => now()->subMonth()->toDateString(),
            'minimum_charge' => 0,
            'bill_arrival_day' => $billArrival,
            'bill_departure_day' => $billDeparture,
            'rounding_mode' => 'nearest',
            'season_length_days' => 90,
        ]);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function bill(array $world, string $start, string $end, string $number = 'SB-1'): ColdStorageBill
    {
        return ColdStorageBill::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'bill_no' => $number,
            'period_start' => $start,
            'period_end' => $end,
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'status' => 'draft',
        ]);
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array{packages: float, weight: float}
     */
    private function balance(array $world, ?string $locationId = null): array
    {
        $query = ColdStorageMovement::query()
            ->where('receipt_item_id', $world['item']->id)
            ->where('location_id', $locationId ?? $world['location']->id);

        return [
            'packages' => round((float) (clone $query)->sum('package_delta'), 3),
            'weight' => round((float) (clone $query)->sum('weight_delta'), 3),
        ];
    }

    /**
     * @return array{merchant: Merchant, business: Business, branch: Branch, customer: Customer, product: Product}
     */
    private function world(): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Cold Store',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret-password',
            'address_line_1' => 'Warehouse road',
            'city' => 'Lahore',
            'status' => Merchant::STATUS_VERIFIED,
            'is_active' => true,
            'cash_in_hand' => 1000,
            'cash_in_bank' => 2000,
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

        return compact('merchant', 'business', 'branch', 'customer', 'product');
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
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('reference')->nullable();
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
            $table->enum('entry_type', ['payment', 'refund', 'adjustment', 'advance'])->default('payment');
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cash_flows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id');
            $table->uuid('business_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('party_type');
            $table->uuid('party_id');
            $table->uuid('settlement_for_id')->nullable();
            $table->string('flow_type')->nullable();
            $table->string('direction');
            $table->decimal('amount', 14, 2);
            $table->date('flow_date');
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

        foreach ([
            '2026_10_12_100000_add_cold_storage_legacy_references',
            '2026_10_12_100100_create_cold_storage_billed_entitlements_table',
            '2026_10_12_100200_add_dispatch_scope_to_cold_storage_bills_table',
            '2026_10_12_100300_create_cold_storage_bill_allocations',
            '2026_10_12_100400_create_cold_storage_opening_balances_and_stock',
            '2026_10_13_190000_drop_cold_storage_opening_balances_table',
            '2026_10_13_191000_drop_cold_storage_opening_stocks_table',
        ] as $name) {
            (require database_path("migrations/{$name}.php"))->up();
        }
    }

    public function test_stock_ageing_reports_days_in_store_for_remaining_lots(): void
    {
        $world = $this->storeGoods(packages: 5, weight: 50);

        $rows = app(ReportService::class)->stockAgeing([
            'merchant_id' => $world['merchant']->id,
            'branch_id' => $world['branch']->id,
        ]);

        $this->assertNotEmpty($rows);
        $this->assertSame(0, $rows[0]['days_in_store']);
    }

    public function test_create_draft_bill_from_stock_builds_preview_lines(): void
    {
        $world = $this->storeGoods();
        $this->rate($world, 10);

        $bill = app(BillingService::class)->createDraftFromStock([
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
        ], null);

        $preview = app(BillingService::class)->preview($bill);

        $this->assertNotEmpty($preview['lines']);
        $this->assertGreaterThan(0, $preview['total']);
    }

    public function test_temperature_alert_can_be_acknowledged(): void
    {
        $world = $this->storeGoods();
        $chamber = $world['chamber'];
        $chamber->update(['min_temperature' => -5, 'max_temperature' => 5]);
        $reading = app(TemperatureService::class)->record($chamber, 12, now(), null);

        $this->assertTrue($reading->is_out_of_range);

        app(ActionAlertService::class)->acknowledgeTemperature($reading, null);

        $this->assertNotNull($reading->refresh()->acknowledged_at);
    }

    public function test_action_alert_digest_uses_dedicated_recipient_list(): void
    {
        Mail::fake();

        $world = $this->storeGoods();
        $chamber = $world['chamber'];
        $chamber->update(['min_temperature' => -5, 'max_temperature' => 5]);
        app(TemperatureService::class)->record($chamber, 12, now(), null);

        $mailer = app(ActionAlertMailService::class);
        $setting = $mailer->settingsForMerchant($world['merchant']->id);
        $setting->update([
            'is_enabled' => true,
            'recipient_emails' => ['ops@example.com', 'not-an-email', 'OPS@example.com'],
        ]);

        $result = $mailer->sendDigestForMerchant($world['merchant']->id, force: true);

        $this->assertStringStartsWith('SENT', $result);
        Mail::assertSent(ColdStorageActionAlertsMailable::class, function (ColdStorageActionAlertsMailable $mail): bool {
            return $mail->hasTo('ops@example.com');
        });
    }

    public function test_customer_ledger_running_balance_matches_debits_and_credits(): void
    {
        $world = $this->world();
        $ledger = app(\App\Services\ColdStorage\CustomerLedgerService::class);

        $this->postedBillFixture($world, 'SB-A', 185500, now()->subDays(10)->toDateString());
        $this->paymentFixture($world, 170800, now()->subDays(5)->toDateString());
        $this->postedBillFixture($world, 'SB-B', 185500, now()->toDateString());

        $statement = $ledger->statement($world['customer'], []);

        $this->assertSame(185500.0, (float) $statement['rows'][0]['balance']);
        $this->assertSame(14700.0, (float) $statement['rows'][1]['balance']);
        $this->assertSame(200200.0, (float) $statement['rows'][2]['balance']);
        $this->assertSame(200200.0, $statement['closing_balance']);
    }

    public function test_customer_ledger_filtered_statement_carries_opening_balance_forward(): void
    {
        $world = $this->world();
        $ledger = app(\App\Services\ColdStorage\CustomerLedgerService::class);

        $this->postedBillFixture($world, 'SB-A', 185500, now()->subDays(10)->toDateString());
        $this->paymentFixture($world, 170800, now()->subDays(5)->toDateString());
        $this->postedBillFixture($world, 'SB-B', 185500, now()->toDateString());

        $filtered = $ledger->statement($world['customer'], ['from' => now()->subDays(2)->toDateString()]);

        $this->assertSame(14700.0, $filtered['opening_balance']);
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame(200200.0, (float) $filtered['rows'][0]['balance']);
    }

    public function test_customer_ledger_does_not_double_count_an_allocated_advance(): void
    {
        $world = $this->storeGoods(packages: 2, weight: 20, on: now()->toDateString());
        $this->rate($world, 10, billArrival: true);
        $bill = $this->bill($world, now()->toDateString(), now()->toDateString());
        $posted = app(BillingService::class)->post($bill, null);

        $billing = app(BillingService::class);
        $advance = $billing->recordAdvancePayment($world['customer'], 1000, now()->toDateString(), 'cash_in_hand', null);
        $billing->allocateAdvanceToBill($advance, $posted, 20, now()->toDateString(), null);

        $ledger = app(\App\Services\ColdStorage\CustomerLedgerService::class);
        $statement = $ledger->statement($world['customer'], []);

        // Bill (debit 20) + advance receipt (credit 1000) = -980. The allocation itself
        // is a zero-value memo row and must not add a second credit.
        $this->assertSame(-980.0, $statement['closing_balance']);
        $this->assertSame(980.0, $statement['unallocated_advance']);

        $allocationRows = array_filter($statement['rows'], fn (array $row): bool => $row['doc_type'] === 'allocation');
        $this->assertCount(1, $allocationRows);
        $this->assertSame(0.0, (float) array_values($allocationRows)[0]['debit']);
        $this->assertSame(0.0, (float) array_values($allocationRows)[0]['credit']);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function postedBillFixture(array $world, string $billNo, float $amount, string $periodEnd): ColdStorageBill
    {
        return ColdStorageBill::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'bill_no' => $billNo,
            'period_start' => $periodEnd,
            'period_end' => $periodEnd,
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'status' => 'posted',
            'storage_total' => $amount,
            'service_total' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'due_amount' => $amount,
            'posted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    private function paymentFixture(array $world, float $amount, string $date): void
    {
        \App\Models\Payment::query()->create([
            'merchant_id' => $world['merchant']->id,
            'paymentable_type' => Customer::class,
            'paymentable_id' => $world['customer']->id,
            'party_type' => Customer::class,
            'party_id' => $world['customer']->id,
            'direction' => 'in',
            'entry_type' => 'payment',
            'amount' => $amount,
            'payment_date' => $date,
            'method' => 'cash_in_hand',
        ]);
    }

    public function test_stock_register_shows_opening_inward_outward_and_closing_per_day(): void
    {
        $world = $this->storeGoods(packages: 90, weight: 90, on: now()->subDays(3)->toDateString());

        $first = $this->makeDispatch($world, $world['customer'], 50, 50);
        $first->update(['dispatched_on' => now()->subDays(2)->toDateString()]);
        app(DispatchService::class)->post($first, null);

        $second = $this->makeDispatch($world, $world['customer'], 40, 40);
        $second->update(['dispatched_on' => now()->subDay()->toDateString()]);
        app(DispatchService::class)->post($second, null);

        try {
            $this->dispatchGoods($world, packages: 1, weight: 1);
            $this->fail('A further release should have failed.');
        } catch (ColdStorageException $exception) {
            $this->assertStringContainsString('available customer stock', $exception->getMessage());
        }

        $register = app(StockRegisterService::class)->register([
            'merchant_id' => $world['merchant']->id,
            'customer_id' => $world['customer']->id,
        ]);

        $rows = $register['groups'][0]['rows'];
        $this->assertSame(90.0, (float) $rows[0]['closing']);
        $this->assertSame(40.0, (float) $rows[1]['closing']);
        $this->assertSame(0.0, (float) $rows[2]['closing']);
    }

    public function test_stock_register_transfer_does_not_change_customer_closing_total(): void
    {
        $world = $this->storeGoods(packages: 90, weight: 90, on: now()->subDays(4)->toDateString());

        $destination = ColdStorageLocation::query()->create([
            'chamber_id' => $world['chamber']->id,
            'name' => 'Bin B',
        ]);
        $transfer = ColdStorageTransfer::query()->create([
            'merchant_id' => $world['merchant']->id,
            'business_id' => $world['business']->id,
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'transfer_no' => 'GT-REG-1',
            'transferred_on' => now()->subDays(3)->toDateString(),
            'status' => 'draft',
        ]);
        ColdStorageTransferLine::query()->create([
            'transfer_id' => $transfer->id,
            'receipt_item_id' => $world['item']->id,
            'from_chamber_id' => $world['chamber']->id,
            'from_location_id' => $world['location']->id,
            'to_chamber_id' => $world['chamber']->id,
            'to_location_id' => $destination->id,
            'package_count' => 10,
            'net_weight' => 10,
        ]);
        app(TransferService::class)->post($transfer, null);

        $withdrawal = $this->makeDispatch($world, $world['customer'], 21, 21);
        $withdrawal->update(['dispatched_on' => now()->subDays(2)->toDateString()]);
        app(DispatchService::class)->post($withdrawal, null);

        $register = app(StockRegisterService::class)->register([
            'merchant_id' => $world['merchant']->id,
            'customer_id' => $world['customer']->id,
        ]);

        $group = $register['groups'][0];
        $this->assertSame(69.0, $group['closing']);

        $transferDay = collect($group['rows'])->firstWhere('date', now()->subDays(3)->toDateString());
        $this->assertSame(0.0, (float) $transferDay['outward'], 'A transfer must not count as an outward release.');
        $this->assertSame(90.0, (float) $transferDay['closing'], 'A transfer must not reduce the customer total.');
    }

    public function test_rent_collection_register_links_withdrawal_bill_and_payment(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(3)->toDateString());
        $this->rate($world, 4);
        $dispatch = $this->dispatchGoods($world, packages: 6, weight: 60);

        $billing = app(BillingService::class);
        $bill = $billing->createDraftFromDispatch($dispatch, null, now()->subDays(3)->toDateString());
        $posted = $billing->post($bill, null);
        $billing->recordPayment($posted, 10, now()->toDateString(), 'cash_in_hand', null);

        $register = app(\App\Services\ColdStorage\RentCollectionRegisterService::class)->register([
            'merchant_id' => $world['merchant']->id,
        ]);

        $row = $register['rows'][0];
        $this->assertSame($dispatch->dispatch_no, $row['withdrawal_reference']);
        $this->assertSame($posted->bill_no, $row['bill_reference']);
        $this->assertSame(6.0, $row['bags']);
        $this->assertSame(10.0, $row['collected']);
        $this->assertGreaterThan(0, $row['due']);
    }

    public function test_rent_collection_register_shows_a_period_bill_without_double_counting(): void
    {
        $world = $this->storeGoods(packages: 10, weight: 100, on: now()->subDays(3)->toDateString());
        $this->rate($world, 4);
        $dispatch = $this->dispatchGoods($world, packages: 6, weight: 60);

        // Billed the older, period-based way ("Bill from stock"), not scoped to
        // this specific withdrawal, which is how most existing bills are created.
        $billing = app(BillingService::class);
        $bill = $billing->createDraftFromStock([
            'branch_id' => $world['branch']->id,
            'customer_id' => $world['customer']->id,
            'period_start' => now()->subDays(3)->toDateString(),
            'period_end' => now()->toDateString(),
        ], null);
        $posted = $billing->post($bill, null);
        $billing->recordPayment($posted, 10, now()->toDateString(), 'cash_in_hand', null);

        $register = app(\App\Services\ColdStorage\RentCollectionRegisterService::class)->register([
            'merchant_id' => $world['merchant']->id,
        ]);

        $withdrawalRow = collect($register['rows'])->firstWhere('withdrawal_reference', $dispatch->dispatch_no);
        $billRow = collect($register['rows'])->first(fn (array $r): bool => str_contains((string) $r['bill_reference'], '(period bill)'));

        // The withdrawal row points at the covering bill for context, but does not
        // claim its own charged/collected/due — that would double-count against
        // the bill's own row once totals are summed.
        $this->assertNotNull($withdrawalRow);
        $this->assertStringContainsString($posted->bill_no, (string) $withdrawalRow['bill_reference']);
        $this->assertNull($withdrawalRow['charged']);
        $this->assertNull($withdrawalRow['collected']);
        $this->assertNull($withdrawalRow['due']);

        $this->assertNotNull($billRow);
        $this->assertSame((float) $posted->total_amount, $billRow['charged']);
        $this->assertSame(10.0, $billRow['collected']);

        $this->assertSame((float) $posted->total_amount, $register['totals']['charged']);
        $this->assertSame(10.0, $register['totals']['collected']);
    }

    public function test_action_alert_digest_skips_when_no_recipients(): void
    {
        Mail::fake();

        $world = $this->storeGoods();
        $mailer = app(ActionAlertMailService::class);
        $mailer->settingsForMerchant($world['merchant']->id)->update([
            'is_enabled' => true,
            'recipient_emails' => [],
        ]);

        $result = $mailer->sendDigestForMerchant($world['merchant']->id, force: true);

        $this->assertStringStartsWith('SKIPPED', $result);
        Mail::assertNothingSent();
    }
}
