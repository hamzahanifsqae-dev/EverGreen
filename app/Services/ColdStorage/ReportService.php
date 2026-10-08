<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageAdjustment;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageTemperatureReading;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private OccupancyService $occupancy) {}

    /**
     * @param  array{merchant_id: string, business_id?: ?string, branch_id?: ?string, customer_id?: ?string, chamber_id?: ?string, from?: ?string, to?: ?string, branch_ids?: list<string>}  $filters
     * @return list<array<string, mixed>>
     */
    public function stockStatement(array $filters): array
    {
        $asOf = $filters;
        unset($asOf['from']);

        $rows = $this->movements($asOf)
            ->selectRaw('customer_id, receipt_item_id, lot_number, product_id, chamber_id, location_id, sum(package_delta) as packages, sum(weight_delta) as weight, max(weight_unit) as weight_unit')
            ->groupBy('customer_id', 'receipt_item_id', 'lot_number', 'product_id', 'chamber_id', 'location_id')
            ->get();

        return $rows->map(fn ($row): array => [
            'customer' => Customer::query()->whereKey($row->customer_id)->value('name'),
            'lot_number' => $row->lot_number,
            'product' => Product::query()->whereKey($row->product_id)->value('name'),
            'chamber' => ColdStorageChamber::query()->whereKey($row->chamber_id)->value('name'),
            'packages' => Quantities::roundQuantity((float) $row->packages),
            'weight' => Quantities::roundQuantity((float) $row->weight),
            'weight_unit' => $row->weight_unit,
        ])->filter(fn (array $row): bool => $row['packages'] > 0 || $row['weight'] > 0)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function occupancy(array $filters): array
    {
        return $this->capacityHeatmap($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function capacityHeatmap(array $filters): array
    {
        $query = ColdStorageChamber::query()->where('merchant_id', $filters['merchant_id']);
        $this->applyChamberFilters($query, $filters);

        return $query->orderBy('name')->get()->map(function (ColdStorageChamber $chamber): array {
            $levels = $this->occupancy->forChamber($chamber);
            $capacity = (float) $levels['capacity'];
            $occupied = (float) $levels['occupied'];
            $percent = $capacity > 0 ? round(($occupied / $capacity) * 100, 1) : 0.0;

            return [
                'chamber' => $chamber->name,
                'business' => $chamber->business?->name,
                'branch' => $chamber->branch?->name,
                'occupied' => $levels['occupied'],
                'available' => $levels['available'],
                'capacity' => $levels['capacity'],
                'unit' => $levels['unit'],
                'occupancy_percent' => $percent,
                'heat' => match (true) {
                    $percent >= 90 => 'critical',
                    $percent >= 75 => 'high',
                    $percent >= 50 => 'medium',
                    default => 'low',
                },
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function stockAgeing(array $filters, int $limit = 100): array
    {
        $query = $this->movements($filters)
            ->join('cold_storage_receipt_items as items', 'cold_storage_movements.receipt_item_id', '=', 'items.id')
            ->join('cold_storage_receipts as receipts', 'items.receipt_id', '=', 'receipts.id')
            ->where('receipts.status', 'posted')
            ->select([
                'cold_storage_movements.customer_id',
                'cold_storage_movements.receipt_item_id',
                'cold_storage_movements.lot_number',
                'cold_storage_movements.product_id',
                'cold_storage_movements.chamber_id',
                DB::raw('MAX(receipts.received_on) as received_on'),
                DB::raw('SUM(cold_storage_movements.package_delta) as packages'),
                DB::raw('SUM(cold_storage_movements.weight_delta) as weight'),
                DB::raw('MAX(cold_storage_movements.weight_unit) as weight_unit'),
            ])
            ->groupBy(
                'cold_storage_movements.customer_id',
                'cold_storage_movements.receipt_item_id',
                'cold_storage_movements.lot_number',
                'cold_storage_movements.product_id',
                'cold_storage_movements.chamber_id',
            )
            ->havingRaw('SUM(cold_storage_movements.package_delta) > 0.0005 OR SUM(cold_storage_movements.weight_delta) > 0.0005');

        $rows = $query->get();
        $today = Carbon::today();

        return $rows->map(function ($row) use ($today): array {
            $receivedOn = Carbon::parse($row->received_on);

            return [
                'customer' => Customer::query()->whereKey($row->customer_id)->value('name'),
                'lot_number' => $row->lot_number,
                'product' => Product::query()->whereKey($row->product_id)->value('name'),
                'chamber' => ColdStorageChamber::query()->whereKey($row->chamber_id)->value('name'),
                'packages' => Quantities::roundQuantity((float) $row->packages),
                'weight' => Quantities::roundQuantity((float) $row->weight),
                'weight_unit' => $row->weight_unit,
                'received_on' => $receivedOn->format(config('cold-storage.date_format')),
                'days_in_store' => (int) $receivedOn->diffInDays($today),
            ];
        })
            ->sortByDesc('days_in_store')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function receipts(array $filters): array
    {
        return $this->documents(ColdStorageReceipt::query(), $filters, 'received_on')
            ->map(fn (ColdStorageReceipt $receipt): array => [
                'number' => $receipt->receipt_no,
                'date' => $receipt->received_on?->format(config('cold-storage.date_format')),
                'customer' => $receipt->customer?->name,
                'status' => $receipt->status,
                'vehicle' => $receipt->vehicle_number,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function dispatches(array $filters): array
    {
        return $this->documents(ColdStorageDispatch::query(), $filters, 'dispatched_on')
            ->map(fn (ColdStorageDispatch $dispatch): array => [
                'number' => $dispatch->dispatch_no,
                'date' => $dispatch->dispatched_on?->format(config('cold-storage.date_format')),
                'customer' => $dispatch->customer?->name,
                'recipient' => $dispatch->recipient_name,
                'status' => $dispatch->status,
                'vehicle' => $dispatch->vehicle_number,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function adjustments(array $filters): array
    {
        return $this->documents(ColdStorageAdjustment::query(), $filters, 'adjusted_on')
            ->map(fn (ColdStorageAdjustment $adjustment): array => [
                'number' => $adjustment->adjustment_no,
                'date' => $adjustment->adjusted_on?->format(config('cold-storage.date_format')),
                'customer' => $adjustment->customer?->name,
                'kind' => $adjustment->kind,
                'reason' => $adjustment->reason,
                'status' => $adjustment->status,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function bills(array $filters): array
    {
        return $this->documents(ColdStorageBill::query()->where('status', 'posted'), $filters, 'period_start')
            ->map(fn (ColdStorageBill $bill): array => [
                'number' => $bill->bill_no,
                'customer' => $bill->customer?->name,
                'period' => $bill->period_start?->format(config('cold-storage.date_format')).' – '.$bill->period_end?->format(config('cold-storage.date_format')),
                'total' => (float) $bill->total_amount,
                'due' => (float) $bill->due_amount,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function temperatureExceptions(array $filters): array
    {
        $query = ColdStorageTemperatureReading::query()
            ->with(['chamber', 'recordedBy'])
            ->where('merchant_id', $filters['merchant_id'])
            ->where('is_out_of_range', true);

        $this->applyRecordFilters($query, $filters);

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('recorded_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('recorded_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('recorded_at')->get()->map(fn (ColdStorageTemperatureReading $reading): array => [
            'chamber' => $reading->chamber?->name,
            'when' => $reading->recorded_at?->format(config('cold-storage.date_format').' H:i'),
            'temperature' => $reading->temperature.' '.$reading->temperature_unit,
            'limits' => trim(($reading->min_temperature ?? '—').' to '.($reading->max_temperature ?? '—')),
            'user' => $reading->recordedBy?->name ?? 'Merchant',
            'source' => $reading->source,
        ])->all();
    }

    /**
     * @param  Builder<ColdStorageMovement>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<ColdStorageMovement>
     */
    private function movements(array $filters): Builder
    {
        $table = (new ColdStorageMovement)->getTable();
        $query = ColdStorageMovement::query()->where($table.'.merchant_id', $filters['merchant_id']);
        $this->applyRecordFilters($query, $filters);

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('occurred_on', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('occurred_on', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TModel>
     */
    private function documents(Builder $query, array $filters, string $dateColumn)
    {
        $query->with('customer')->where($query->getModel()->getTable().'.merchant_id', $filters['merchant_id']);
        $this->applyRecordFilters($query, $filters);

        if (filled($filters['from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }

        return $query->latest($dateColumn)->get();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyRecordFilters(Builder $query, array $filters): void
    {
        $table = $query->getModel()->getTable();

        if (filled($filters['business_id'] ?? null)) {
            $query->where($table.'.business_id', $filters['business_id']);
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->where($table.'.branch_id', $filters['branch_id']);
        }

        if (filled($filters['customer_id'] ?? null) && $query->getModel()->isFillable('customer_id')) {
            $query->where($table.'.customer_id', $filters['customer_id']);
        }

        if (filled($filters['chamber_id'] ?? null) && $query->getModel()->isFillable('chamber_id')) {
            $query->where($table.'.chamber_id', $filters['chamber_id']);
        }

        if (($filters['restrict_branches'] ?? false) === true) {
            $branchIds = $filters['branch_ids'] ?? [];
            $query->whereIn($table.'.branch_id', $branchIds === [] ? ['00000000-0000-0000-0000-000000000000'] : $branchIds);
        }
    }

    /**
     * @param  Builder<ColdStorageChamber>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyChamberFilters(Builder $query, array $filters): void
    {
        if (filled($filters['business_id'] ?? null)) {
            $query->where('business_id', $filters['business_id']);
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (filled($filters['chamber_id'] ?? null)) {
            $query->whereKey($filters['chamber_id']);
        }

        if (($filters['restrict_branches'] ?? false) === true) {
            $branchIds = $filters['branch_ids'] ?? [];
            $query->whereIn('branch_id', $branchIds === [] ? ['00000000-0000-0000-0000-000000000000'] : $branchIds);
        }
    }

    /**
     * @return list<string>
     */
    public static function assignedBranchIds(User $user): array
    {
        return $user->branches()->pluck('branches.id')->all();
    }
}
