<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Collection;

class CustomerStorageOverviewService
{
    /**
     * @return array{
     *     customer: Customer,
     *     packages_received: float,
     *     weight_received: float,
     *     packages_returned: float,
     *     weight_returned: float,
     *     packages_on_hand: float,
     *     weight_on_hand: float,
     *     receipts_posted: int,
     *     receipts_draft: int,
     *     returns_posted: int,
     *     returns_draft: int,
     *     receipts: list<array<string, mixed>>,
     *     returns: list<array<string, mixed>>,
     *     stock_on_hand: list<array<string, mixed>>,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        $merchantId = (string) $customer->merchant_id;
        $customerId = (string) $customer->id;

        $receipts = ColdStorageReceipt::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->with(['branch', 'items'])
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->get();

        $returns = ColdStorageDispatch::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->with(['branch', 'lines'])
            ->orderByDesc('dispatched_on')
            ->orderByDesc('created_at')
            ->get();

        $postedReceiptIds = $receipts->where('status', 'posted')->pluck('id');
        $postedReturnIds = $returns->where('status', 'posted')->pluck('id');

        $packagesReceived = Quantities::roundQuantity((float) ColdStorageReceiptItem::query()
            ->whereIn('receipt_id', $postedReceiptIds)
            ->sum('package_count'));
        $weightReceived = Quantities::roundQuantity((float) ColdStorageReceiptItem::query()
            ->whereIn('receipt_id', $postedReceiptIds)
            ->sum('net_weight'));

        $packagesReturned = Quantities::roundQuantity((float) ColdStorageDispatchLine::query()
            ->whereIn('dispatch_id', $postedReturnIds)
            ->sum('package_count'));
        $weightReturned = Quantities::roundQuantity((float) ColdStorageDispatchLine::query()
            ->whereIn('dispatch_id', $postedReturnIds)
            ->sum('net_weight'));

        $stockOnHand = $this->stockOnHand($merchantId, $customerId);
        $packagesOnHand = Quantities::roundQuantity((float) collect($stockOnHand)->sum('packages'));
        $weightOnHand = Quantities::roundQuantity((float) collect($stockOnHand)->sum('weight'));

        return [
            'customer' => $customer,
            'packages_received' => $packagesReceived,
            'weight_received' => $weightReceived,
            'packages_returned' => $packagesReturned,
            'weight_returned' => $weightReturned,
            'packages_on_hand' => $packagesOnHand,
            'weight_on_hand' => $weightOnHand,
            'receipts_posted' => $receipts->where('status', 'posted')->count(),
            'receipts_draft' => $receipts->where('status', 'draft')->count(),
            'returns_posted' => $returns->where('status', 'posted')->count(),
            'returns_draft' => $returns->where('status', 'draft')->count(),
            'receipts' => $receipts->map(fn (ColdStorageReceipt $receipt): array => [
                'id' => $receipt->id,
                'number' => $receipt->receipt_no,
                'date' => $receipt->received_on?->format(config('cold-storage.date_format')),
                'branch' => $receipt->branch?->name,
                'status' => $receipt->status,
                'packages' => Quantities::roundQuantity((float) $receipt->items->sum('package_count')),
                'weight' => Quantities::roundQuantity((float) $receipt->items->sum('net_weight')),
            ])->all(),
            'returns' => $returns->map(fn (ColdStorageDispatch $dispatch): array => [
                'id' => $dispatch->id,
                'number' => $dispatch->dispatch_no,
                'date' => $dispatch->dispatched_on?->format(config('cold-storage.date_format')),
                'branch' => $dispatch->branch?->name,
                'status' => $dispatch->status,
                'packages' => Quantities::roundQuantity((float) $dispatch->lines->sum('package_count')),
                'weight' => Quantities::roundQuantity((float) $dispatch->lines->sum('net_weight')),
            ])->all(),
            'stock_on_hand' => $stockOnHand,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockOnHand(string $merchantId, string $customerId): array
    {
        /** @var Collection<int, object> $rows */
        $rows = ColdStorageMovement::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->selectRaw('lot_number, product_id, chamber_id, sum(package_delta) as packages, sum(weight_delta) as weight, max(weight_unit) as weight_unit')
            ->groupBy('lot_number', 'product_id', 'chamber_id')
            ->havingRaw('SUM(package_delta) > 0.0005 OR SUM(weight_delta) > 0.0005')
            ->get();

        return $rows->map(function ($row): array {
            return [
                'lot_number' => $row->lot_number,
                'product' => Product::query()->whereKey($row->product_id)->value('name'),
                'chamber' => ColdStorageChamber::query()->whereKey($row->chamber_id)->value('name'),
                'packages' => Quantities::roundQuantity((float) $row->packages),
                'weight' => Quantities::roundQuantity((float) $row->weight),
                'weight_unit' => $row->weight_unit,
            ];
        })->values()->all();
    }
}
