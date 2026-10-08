<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageReservation;
use App\Models\ColdStorageTemperatureReading;
use App\Models\User;
use App\Services\ColdStorage\ActionAlertService;
use App\Services\ColdStorage\ReportService;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ColdStorageActionAlerts extends Page implements HasTable
{
    use ColdStorageSubmoduleNavigation;
    use InteractsWithTable;

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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => app(ActionAlertService::class)
                ->temperatureQuery($this->reportFilters())
                ->where('is_out_of_range', true)
                ->whereNull('acknowledged_at')
                ->with(['chamber', 'branch']))
            ->columns([
                TextColumn::make('recorded_at')->label('When')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('chamber.name')->label('Chamber'),
                TextColumn::make('temperature')->label('Reading'),
                TextColumn::make('min_temperature')->label('Min'),
                TextColumn::make('max_temperature')->label('Max'),
                TextColumn::make('source')->badge(),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->visible(fn (): bool => ColdStorageAccess::can('update'))
                    ->action(function (ColdStorageTemperatureReading $record): void {
                        app(ActionAlertService::class)->acknowledgeTemperature(
                            $record,
                            ColdStorageAccess::actorId(),
                        );

                        Notification::make()
                            ->title('Temperature alert acknowledged')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->emptyStateHeading('No open temperature alerts');
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
            'temperature' => $alerts->unacknowledgedTemperatureCount($filters),
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
