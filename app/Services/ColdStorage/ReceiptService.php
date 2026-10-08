<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageBillLine;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(
        private StockLedger $ledger,
        private DocumentReversal $reversal,
    ) {}

    public function post(ColdStorageReceipt $receipt, ?string $actorId): ColdStorageReceipt
    {
        return DB::transaction(function () use ($receipt, $actorId): ColdStorageReceipt {
            /** @var ColdStorageReceipt $receipt */
            $receipt = ColdStorageReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($receipt->status !== 'draft') {
                throw ColdStorageException::make('Only a draft goods receipt can be posted.');
            }

            $receipt->load('items.allocations');

            if ($receipt->items->isEmpty()) {
                throw ColdStorageException::make('Add at least one goods line before posting.');
            }

            $this->assertCustomer($receipt);

            foreach ($receipt->items as $item) {
                /** @var ColdStorageReceiptItem $item */
                $this->assertItem($receipt, $item);

                foreach ($item->allocations as $allocation) {
                    $this->ledger->post([
                        'merchant_id' => $receipt->merchant_id,
                        'business_id' => $receipt->business_id,
                        'branch_id' => $receipt->branch_id,
                        'customer_id' => $receipt->customer_id,
                        'receipt_item_id' => $item->id,
                        'chamber_id' => $allocation->chamber_id,
                        'location_id' => $allocation->location_id,
                        'movement_type' => 'receive',
                        'package_delta' => (float) $allocation->package_count,
                        'weight_delta' => (float) $allocation->net_weight,
                        'reference' => $receipt,
                        'reason' => null,
                        'occurred_on' => Carbon::parse($receipt->received_on)->toDateString(),
                        'created_by' => $actorId,
                    ]);
                }
            }

            $receipt->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            return $receipt->refresh();
        });
    }

    public function cancel(ColdStorageReceipt $receipt, ?string $actorId, string $reason): ColdStorageReceipt
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($receipt, $actorId, $reason): ColdStorageReceipt {
            /** @var ColdStorageReceipt $receipt */
            $receipt = ColdStorageReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($receipt->status !== 'posted') {
                throw ColdStorageException::make('Only a posted goods receipt can be cancelled.');
            }

            $itemIds = $receipt->items()->pluck('id')->all();

            $billed = ColdStorageBillLine::query()
                ->whereIn('receipt_item_id', $itemIds)
                ->whereIn('bill_id', ColdStorageBill::query()->where('status', 'posted')->select('id'))
                ->exists();

            if ($billed) {
                throw ColdStorageException::make('Cancel the storage bills that cover this receipt before reversing it.');
            }

            $this->reversal->reverse($receipt, $actorId, $reason, now()->toDateString());

            $receipt->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
            ]);

            return $receipt->refresh();
        });
    }

    private function assertCustomer(ColdStorageReceipt $receipt): void
    {
        $ownsCustomer = Customer::query()
            ->whereKey($receipt->customer_id)
            ->where('merchant_id', $receipt->merchant_id)
            ->exists();

        if (! $ownsCustomer) {
            throw ColdStorageException::make('The customer does not belong to this merchant.');
        }
    }

    private function assertItem(ColdStorageReceipt $receipt, ColdStorageReceiptItem $item): void
    {
        if (trim((string) $item->lot_number) === '') {
            throw ColdStorageException::make('Each goods line needs a lot number.');
        }

        if ((float) $item->package_count <= 0 || (float) $item->net_weight <= 0) {
            throw ColdStorageException::make('Package count and net weight must be greater than zero.');
        }

        if (! in_array((string) $item->weight_unit, ['kilogram', 'tonne'], true)) {
            throw ColdStorageException::make('Weight must be recorded in kilograms or tonnes.');
        }

        $ownsProduct = Product::query()
            ->whereKey($item->product_id)
            ->where('merchant_id', $receipt->merchant_id)
            ->exists();

        if (! $ownsProduct) {
            throw ColdStorageException::make('The product does not belong to this merchant.');
        }

        if ($item->allocations->isEmpty()) {
            throw ColdStorageException::make('Each goods line needs a chamber allocation.');
        }

        $packages = Quantities::roundQuantity((float) $item->allocations->sum('package_count'));
        $weight = Quantities::roundQuantity((float) $item->allocations->sum('net_weight'));

        if (
            Quantities::exceeds(abs($packages - (float) $item->package_count), 0)
            || Quantities::exceeds(abs($weight - (float) $item->net_weight), 0)
        ) {
            throw ColdStorageException::make('Location allocations must add up to the line quantity and weight.');
        }
    }
}
