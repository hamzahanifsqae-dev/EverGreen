<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageAdjustment;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageTransfer;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Support\ColdStorageAccess;

class StockRegisterService
{
    /**
     * Dated register per customer/lot: opening quantity, inward, outward, adjustment
     * and closing quantity for each day something moved. Transfers only relocate
     * stock inside the store, so they never count as inward/outward in the totals —
     * they only affect the chamber/location breakdown when one is filtered.
     *
     * @param  array{customer_id?: ?string, lot_number?: ?string, branch_id?: ?string, chamber_id?: ?string, location_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array{
     *     groups: list<array{
     *         customer: string,
     *         lot_number: string,
     *         product: string,
     *         opening: float,
     *         closing: float,
     *         rows: list<array<string, mixed>>,
     *     }>,
     * }
     */
    public function register(array $filters, Merchant|User|null $user = null): array
    {
        $merchantId = (string) $filters['merchant_id'];
        $branchIds = $this->resolveBranchIds($filters, $user);

        $query = ColdStorageMovement::query()
            ->where('merchant_id', $merchantId)
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(filled($filters['customer_id'] ?? null), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(filled($filters['lot_number'] ?? null), fn ($q) => $q->where('lot_number', $filters['lot_number']))
            ->when(filled($filters['chamber_id'] ?? null), fn ($q) => $q->where('chamber_id', $filters['chamber_id']))
            ->when(filled($filters['location_id'] ?? null), fn ($q) => $q->where('location_id', $filters['location_id']));

        $movements = $query->orderBy('occurred_on')->orderBy('created_at')->get();

        $customers = Customer::query()->whereIn('id', $movements->pluck('customer_id')->unique())->pluck('name', 'id');
        $products = Product::query()->whereIn('id', $movements->pluck('product_id')->unique())->pluck('name', 'id');

        $groups = [];

        foreach ($movements->groupBy(fn (ColdStorageMovement $m): string => $m->customer_id.'|'.$m->lot_number) as $key => $lotMovements) {
            $byDay = $lotMovements->groupBy(fn (ColdStorageMovement $m): string => $m->occurred_on->toDateString());
            $opening = 0.0;
            $running = 0.0;
            $rows = [];

            foreach ($byDay->sortKeys() as $date => $dayMovements) {
                if (($filters['to'] ?? null) !== null && $date > $filters['to']) {
                    continue;
                }

                $inward = (float) $dayMovements->where('movement_type', 'receive')->sum('package_delta');
                $outward = abs((float) $dayMovements->whereIn('movement_type', ['dispatch', 'damage'])->sum('package_delta'));
                $adjustment = (float) $dayMovements->where('movement_type', 'adjustment')->sum('package_delta');
                $transferNet = (float) $dayMovements->whereIn('movement_type', ['transfer_in', 'transfer_out'])->sum('package_delta');
                $reversal = (float) $dayMovements->where('movement_type', 'reversal')->sum('package_delta');

                $running = Quantities::roundQuantity($running + $inward - $outward + $adjustment + $transferNet + $reversal);

                if (($filters['from'] ?? null) !== null && $date < $filters['from']) {
                    $opening = $running;

                    continue;
                }

                $rows[] = [
                    'date' => $date,
                    'inward' => Quantities::roundQuantity($inward),
                    'outward' => Quantities::roundQuantity($outward),
                    'adjustment' => Quantities::roundQuantity($adjustment + $transferNet + $reversal),
                    'closing' => $running,
                    'references' => $this->referencesFor($dayMovements),
                ];
            }

            $first = $lotMovements->first();

            $groups[] = [
                'customer' => $customers[$first->customer_id] ?? '—',
                'lot_number' => $first->lot_number,
                'product' => $products[$first->product_id] ?? '—',
                'opening' => $opening,
                'closing' => $rows === [] ? $opening : (float) end($rows)['closing'],
                'rows' => $rows,
            ];
        }

        return ['groups' => $groups];
    }

    /**
     * @param  array{branch_id?: ?string}  $filters
     * @return list<string>|null
     */
    private function resolveBranchIds(array $filters, Merchant|User|null $user): ?array
    {
        $authorized = ColdStorageAccess::authorizedBranchIds($user);
        $requested = filled($filters['branch_id'] ?? null) ? [(string) $filters['branch_id']] : null;

        if ($requested === null) {
            return $authorized;
        }

        if ($authorized === null) {
            return $requested;
        }

        return array_values(array_intersect($authorized, $requested));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ColdStorageMovement>  $dayMovements
     * @return list<string>
     */
    private function referencesFor($dayMovements): array
    {
        return $dayMovements
            ->map(function (ColdStorageMovement $movement): ?string {
                return match ($movement->reference_type) {
                    ColdStorageReceipt::class => ColdStorageReceipt::query()->whereKey($movement->reference_id)->value('receipt_no'),
                    ColdStorageDispatch::class => ColdStorageDispatch::query()->whereKey($movement->reference_id)->value('dispatch_no'),
                    ColdStorageTransfer::class => ColdStorageTransfer::query()->whereKey($movement->reference_id)->value('transfer_no'),
                    ColdStorageAdjustment::class => ColdStorageAdjustment::query()->whereKey($movement->reference_id)->value('adjustment_no'),
                    default => null,
                };
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
