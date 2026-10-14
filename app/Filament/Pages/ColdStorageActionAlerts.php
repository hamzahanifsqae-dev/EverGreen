<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageReservation;
use App\Models\User;
use App\Services\ColdStorage\ActionAlertService;
use App\Services\ColdStorage\ReportService;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ColdStorageActionAlerts extends Page
{
    use ColdStorageSubmoduleNavigation;

    protected static function coldStorageUiModuleKey(): ?string
    {
        return 'cold_storage_action_alerts';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    protected static ?string $navigationLabel = 'Action alerts';

    protected static ?string $title = 'Action alerts';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.cold-storage-action-alerts';

    public static function canAccess(): bool
    {
        return ColdStorageAccess::can('view');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $alerts = app(ActionAlertService::class);
        $filters = $this->reportFilters();

        return [
            'open' => $alerts->openCount($filters),
            'bills' => $alerts->overdueBillCount($filters),
            'reservations' => $alerts->upcomingReservationCount($filters),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overdueBills(): array
    {
        return app(ActionAlertService::class)
            ->billQuery($this->reportFilters())
            ->where('status', 'posted')
            ->where('due_amount', '>', 0)
            ->with(['customer', 'branch'])
            ->orderByDesc('due_amount')
            ->limit(10)
            ->get()
            ->map(fn (ColdStorageBill $bill): array => [
                'bill_no' => $bill->bill_no,
                'customer' => $bill->customer?->name,
                'branch' => $bill->branch?->name,
                'due_amount' => (float) $bill->due_amount,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upcomingReservations(): array
    {
        $today = now()->toDateString();
        $horizon = now()->addDays(7)->toDateString();

        return app(ActionAlertService::class)
            ->reservationQuery($this->reportFilters())
            ->where('status', 'confirmed')
            ->whereDate('reserved_from', '>=', $today)
            ->whereDate('reserved_from', '<=', $horizon)
            ->with(['customer', 'branch', 'chamber'])
            ->orderBy('reserved_from')
            ->limit(10)
            ->get()
            ->map(fn (ColdStorageReservation $reservation): array => [
                'reservation_no' => $reservation->reservation_no,
                'customer' => $reservation->customer?->name,
                'branch' => $reservation->branch?->name,
                'chamber' => $reservation->chamber?->name,
                'reserved_from' => $reservation->reserved_from?->format(config('cold-storage.date_format')),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFilters(): array
    {
        $user = ColdStorageAccess::user();

        return [
            'merchant_id' => ColdStorageAccess::merchantId($user),
            'restrict_branches' => $user instanceof User,
            'branch_ids' => $user instanceof User ? ReportService::assignedBranchIds($user) : [],
        ];
    }
}
