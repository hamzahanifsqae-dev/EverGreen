<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ColdStorageActionAlerts;
use App\Filament\Pages\ColdStorageCapacityHeatmap;
use App\Filament\Pages\ColdStorageLotAgeing;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageReceipt;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\ColdStorage\ActionAlertService;
use App\Services\ColdStorage\OccupancyService;
use App\Services\ColdStorage\ReportService;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class ColdStorageStatsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.cold-storage-stats-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return UiModules::enabled('cold_storage')
            && ColdStorageAccess::can('view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $merchantId = ColdStorageAccess::merchantId();
        $filters = $this->filters();
        $currency = config('cold-storage.currency', 'PKR');

        if ($merchantId === null) {
            return [
                'stats' => $this->emptyStats(),
                'trend' => $this->emptyTrend(),
                'stock' => $this->emptyStock(),
                'occupancy' => $this->emptyOccupancy(),
                'leaders' => ['customers' => []],
                'currency' => $currency,
                'filterPeriodLabel' => $this->filterPeriodLabel($filters),
                'insights' => $this->emptyInsights(),
            ];
        }

        $chamberQuery = ColdStorageAccess::scope(ColdStorageChamber::query()->where('is_active', true));
        $this->applyBranchFilters($chamberQuery, $filters);

        // Overview document counts are live (business/branch only). Date filters
        // remain for billing and the Operations Pulse charts.
        $receiptQuery = ColdStorageAccess::scope(ColdStorageReceipt::query());
        $this->applyBranchFilters($receiptQuery, $filters);

        $dispatchQuery = ColdStorageAccess::scope(ColdStorageDispatch::query());
        $this->applyBranchFilters($dispatchQuery, $filters);

        $billQuery = ColdStorageAccess::scope(ColdStorageBill::query()->where('status', 'posted'));
        $this->applyBillPeriodFilters($billQuery, $filters);

        $stockTotals = ColdStorageMovement::query()
            ->where('merchant_id', $merchantId)
            ->when($filters['business_id'], fn ($query, $id) => $query->where('business_id', $id))
            ->when($filters['branch_id'], fn ($query, $id) => $query->where('branch_id', $id))
            ->selectRaw('SUM(package_delta) as packages')
            ->selectRaw('SUM(weight_delta) as weight')
            ->first();

        $stockLots = ColdStorageMovement::query()
            ->where('merchant_id', $merchantId)
            ->when($filters['business_id'], fn ($query, $id) => $query->where('business_id', $id))
            ->when($filters['branch_id'], fn ($query, $id) => $query->where('branch_id', $id))
            ->select('receipt_item_id')
            ->selectRaw('SUM(package_delta) as packages')
            ->selectRaw('SUM(weight_delta) as weight')
            ->groupBy('receipt_item_id')
            ->havingRaw('SUM(package_delta) > 0.0005 OR SUM(weight_delta) > 0.0005')
            ->count();

        $reportFilters = $this->reportFilters($filters);
        $alerts = app(ActionAlertService::class);
        $tempExceptions = $alerts->unacknowledgedTemperatureCount($reportFilters);
        $actionAlertsOpen = UiModules::enabled('cold_storage_action_alerts')
            ? $alerts->openCount($reportFilters)
            : null;

        $dueAmount = (float) (clone $billQuery)->sum('due_amount');
        $billedAmount = (float) (clone $billQuery)->sum('total_amount');
        $receiptsPosted = (clone $receiptQuery)->where('status', 'posted')->count();
        $dispatchesPosted = (clone $dispatchQuery)->where('status', 'posted')->count();
        $receiptsDraft = (clone $receiptQuery)->where('status', 'draft')->count();
        $dispatchesDraft = (clone $dispatchQuery)->where('status', 'draft')->count();

        $receiptPackages = (float) ColdStorageReceiptItem::query()
            ->whereIn('receipt_id', (clone $receiptQuery)->where('status', 'posted')->select('id'))
            ->sum('package_count');

        $dispatchPackages = (float) ColdStorageDispatchLine::query()
            ->whereIn('dispatch_id', (clone $dispatchQuery)->where('status', 'posted')->select('id'))
            ->sum('package_count');

        $chambers = (clone $chamberQuery)->get();

        return [
            'stats' => [
                'chambers' => $chambers->count(),
                'stock_lots' => $stockLots,
                'receipts_posted' => $receiptsPosted,
                'dispatches_posted' => $dispatchesPosted,
                'receipts_draft' => $receiptsDraft,
                'dispatches_draft' => $dispatchesDraft,
                'receipt_packages' => $receiptPackages,
                'dispatch_packages' => $dispatchPackages,
                'bills_due' => (clone $billQuery)->where('due_amount', '>', 0)->count(),
                'due_amount' => $dueAmount,
                'billed_amount' => $billedAmount,
                'temp_exceptions' => $tempExceptions,
                'action_alerts_open' => $actionAlertsOpen,
                'customers' => UiModules::enabled('customers')
                    ? Customer::query()->where('merchant_id', $merchantId)->count()
                    : null,
                'products' => UiModules::enabled('products')
                    ? Product::query()->where('merchant_id', $merchantId)->where('is_active', true)->count()
                    : null,
            ],
            'stock' => [
                'packages' => max(0, (float) ($stockTotals->packages ?? 0)),
                'weight' => max(0, (float) ($stockTotals->weight ?? 0)),
                'lots' => $stockLots,
            ],
            'occupancy' => $this->occupancySummary($chambers),
            'trend' => $this->trendData($filters),
            'leaders' => [
                'customers' => $this->topCustomersByReceipts($filters),
            ],
            'currency' => $currency,
            'filterPeriodLabel' => $this->filterPeriodLabel($filters),
            'insights' => $this->insights($reportFilters, $chambers),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function emptyStats(): array
    {
        return [
            'chambers' => 0,
            'stock_lots' => 0,
            'receipts_posted' => 0,
            'dispatches_posted' => 0,
            'receipts_draft' => 0,
            'dispatches_draft' => 0,
            'receipt_packages' => 0.0,
            'dispatch_packages' => 0.0,
            'bills_due' => 0,
            'due_amount' => 0.0,
            'billed_amount' => 0.0,
            'temp_exceptions' => 0,
            'action_alerts_open' => null,
            'customers' => UiModules::enabled('customers') ? 0 : null,
            'products' => UiModules::enabled('products') ? 0 : null,
        ];
    }

    /**
     * @return array{packages: float, weight: float, lots: int}
     */
    private function emptyStock(): array
    {
        return [
            'packages' => 0.0,
            'weight' => 0.0,
            'lots' => 0,
        ];
    }

    /**
     * @return array{capacity: float, occupied: float, available: float, unit: string}
     */
    private function emptyOccupancy(): array
    {
        return [
            'capacity' => 0.0,
            'occupied' => 0.0,
            'available' => 0.0,
            'unit' => 'kg',
        ];
    }

    /**
     * @return array{labels: list<string>, receipts: list<int>, dispatches: list<int>}
     */
    private function emptyTrend(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset));

        return [
            'labels' => $months->map(fn (Carbon $date) => $date->format('M'))->values()->all(),
            'receipts' => $months->map(fn () => 0)->values()->all(),
            'dispatches' => $months->map(fn () => 0)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, ColdStorageChamber>  $chambers
     * @return array{capacity: float, occupied: float, available: float, unit: string}
     */
    private function occupancySummary(Collection $chambers): array
    {
        $occupancy = app(OccupancyService::class);
        $capacity = 0.0;
        $occupied = 0.0;
        $unit = 'kg';

        foreach ($chambers as $chamber) {
            $levels = $occupancy->forChamber($chamber);
            $capacity += (float) $levels['capacity'];
            $occupied += (float) $levels['occupied'];
            $unit = (string) $levels['unit'];
        }

        return [
            'capacity' => $capacity,
            'occupied' => $occupied,
            'available' => max(0, $capacity - $occupied),
            'unit' => $unit,
        ];
    }

    /**
     * @param  array{business_id: ?string, branch_id: ?string, date_from: ?string, date_to: ?string}  $filters
     * @return array{labels: list<string>, receipts: list<int>, dispatches: list<int>}
     */
    private function trendData(array $filters): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset));

        $labels = $months->map(fn (Carbon $date) => $date->format('M'))->values();
        $receiptSeries = $months->map(fn () => 0)->values();
        $dispatchSeries = $months->map(fn () => 0)->values();

        foreach ($months as $index => $month) {
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $receiptQuery = ColdStorageAccess::scope(
                ColdStorageReceipt::query()->where('status', 'posted')
            );
            $this->applyBranchFilters($receiptQuery, $filters);

            $dispatchQuery = ColdStorageAccess::scope(
                ColdStorageDispatch::query()->where('status', 'posted')
            );
            $this->applyBranchFilters($dispatchQuery, $filters);

            $receiptSeries[$index] = (clone $receiptQuery)
                ->whereDate('received_on', '>=', $start)
                ->whereDate('received_on', '<=', $end)
                ->count();

            $dispatchSeries[$index] = (clone $dispatchQuery)
                ->whereDate('dispatched_on', '>=', $start)
                ->whereDate('dispatched_on', '<=', $end)
                ->count();
        }

        return [
            'labels' => $labels->all(),
            'receipts' => $receiptSeries->all(),
            'dispatches' => $dispatchSeries->all(),
        ];
    }

    /**
     * @param  array{business_id: ?string, branch_id: ?string, date_from: ?string, date_to: ?string}  $filters
     * @return list<array{name: string, count: int, amount: float}>
     */
    private function topCustomersByReceipts(array $filters): array
    {
        $query = ColdStorageAccess::scope(
            ColdStorageReceipt::query()->where('status', 'posted')
        );
        $this->applyDocumentFilters($query, $filters, 'received_on');

        return $query
            ->selectRaw('customer_id, COUNT(*) as receipt_count')
            ->groupBy('customer_id')
            ->orderByDesc('receipt_count')
            ->limit(3)
            ->get()
            ->map(function ($row): array {
                return [
                    'name' => Customer::query()->whereKey($row->customer_id)->value('name') ?? 'Unknown',
                    'count' => (int) $row->receipt_count,
                    'amount' => (float) $row->receipt_count,
                ];
            })
            ->all();
    }

    /**
     * @param  array{business_id: ?string, branch_id: ?string, date_from: ?string, date_to: ?string}  $filters
     */
    private function filterPeriodLabel(array $filters): string
    {
        if ($filters['date_from'] && $filters['date_to']) {
            return Carbon::parse($filters['date_from'])->format('d M Y')
                .' – '
                .Carbon::parse($filters['date_to'])->format('d M Y');
        }

        if ($filters['date_from']) {
            return 'From '.Carbon::parse($filters['date_from'])->format('d M Y');
        }

        if ($filters['date_to']) {
            return 'Until '.Carbon::parse($filters['date_to'])->format('d M Y');
        }

        return 'All time';
    }

    /**
     * @return array{business_id: ?string, branch_id: ?string, date_from: ?string, date_to: ?string}
     */
    private function filters(): array
    {
        return [
            'business_id' => $this->pageFilters['business_id'] ?? null,
            'branch_id' => $this->pageFilters['branch_id'] ?? null,
            'date_from' => $this->normalizeFilterDate($this->pageFilters['date_from'] ?? null),
            'date_to' => $this->normalizeFilterDate($this->pageFilters['date_to'] ?? null),
        ];
    }

    private function normalizeFilterDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyBranchFilters($query, array $filters): void
    {
        $query
            ->when($filters['business_id'], fn ($q, $id) => $q->where('business_id', $id))
            ->when($filters['branch_id'], fn ($q, $id) => $q->where('branch_id', $id));
    }

    private function applyDocumentFilters($query, array $filters, string $dateColumn): void
    {
        $this->applyBranchFilters($query, $filters);
        $query
            ->when($filters['date_from'], fn ($q, $date) => $q->whereDate($dateColumn, '>=', $date))
            ->when($filters['date_to'], fn ($q, $date) => $q->whereDate($dateColumn, '<=', $date));
    }

    /**
     * Bills match the filter when their billing period overlaps the selected dates.
     */
    private function applyBillPeriodFilters($query, array $filters): void
    {
        $this->applyBranchFilters($query, $filters);

        $query
            ->when(
                $filters['date_from'],
                fn ($q, $date) => $q->whereDate('period_end', '>=', $date),
            )
            ->when(
                $filters['date_to'],
                fn ($q, $date) => $q->whereDate('period_start', '<=', $date),
            );
    }

    /**
     * @param  array{business_id: ?string, branch_id: ?string, date_from: ?string, date_to: ?string}  $filters
     * @return array<string, mixed>
     */
    private function reportFilters(array $filters): array
    {
        $user = ColdStorageAccess::user();

        return [
            'merchant_id' => ColdStorageAccess::merchantId($user),
            'business_id' => $filters['business_id'] ?? null,
            'branch_id' => $filters['branch_id'] ?? null,
            'restrict_branches' => $user instanceof User,
            'branch_ids' => $user instanceof User ? ReportService::assignedBranchIds($user) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $reportFilters
     * @param  Collection<int, ColdStorageChamber>  $chambers
     * @return array<string, mixed>
     */
    private function insights(array $reportFilters, Collection $chambers): array
    {
        $reports = app(ReportService::class);
        $occupancy = app(OccupancyService::class);

        $heatmap = UiModules::enabled('cold_storage_capacity_heatmap')
            ? collect($reports->capacityHeatmap($reportFilters))->take(5)->all()
            : [];

        $ageing = UiModules::enabled('cold_storage_lot_ageing')
            ? $reports->stockAgeing($reportFilters, 5)
            : [];

        $chamberHeat = $chambers->map(function (ColdStorageChamber $chamber) use ($occupancy): array {
            $levels = $occupancy->forChamber($chamber);
            $capacity = (float) $levels['capacity'];
            $percent = $capacity > 0 ? round(((float) $levels['occupied'] / $capacity) * 100, 1) : 0.0;

            return [
                'name' => $chamber->name,
                'percent' => $percent,
            ];
        })->sortByDesc('percent')->take(5)->values()->all();

        return [
            'urls' => [
                'alerts' => UiModules::enabled('cold_storage_action_alerts') ? ColdStorageActionAlerts::getUrl() : null,
                'ageing' => UiModules::enabled('cold_storage_lot_ageing') ? ColdStorageLotAgeing::getUrl() : null,
                'heatmap' => UiModules::enabled('cold_storage_capacity_heatmap') ? ColdStorageCapacityHeatmap::getUrl() : null,
            ],
            'heatmap' => $heatmap,
            'ageing' => $ageing,
            'chamber_heat' => UiModules::enabled('cold_storage_capacity_heatmap') ? $chamberHeat : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyInsights(): array
    {
        return [
            'urls' => ['alerts' => null, 'ageing' => null, 'heatmap' => null],
            'heatmap' => [],
            'ageing' => [],
            'chamber_heat' => [],
        ];
    }
}
