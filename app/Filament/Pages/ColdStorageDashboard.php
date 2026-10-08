<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Models\Branch;
use App\Models\Business;
use App\Models\ColdStorageChamber;
use App\Models\User;
use App\Services\ColdStorage\ReportService;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ColdStorageDashboard extends Page
{
    use HasFiltersForm;
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'cold_storage';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    protected static ?string $navigationLabel = 'Cold storage dashboard';

    protected static ?string $title = 'Cold storage';

    protected static ?int $navigationSort = 0;

    /**
     * Hidden from the sidebar — the home Dashboard already shows the cold storage overview.
     * Keep this page for detailed stock/occupancy report tables if re-enabled later.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.cold-storage-dashboard';

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return ColdStorageAccess::can('view');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Filters')
                    ->description('Limit the reports by business, branch, customer, chamber, and date.')
                    ->extraAttributes(['class' => 'dashboard-filters-section'])
                    ->schema([
                        Select::make('business_id')
                            ->label('Business')
                            ->placeholder('All businesses')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->businessOptions())
                            ->live()
                            ->afterStateUpdated(function (callable $set): void {
                                $set('branch_id', null);
                                $set('chamber_id', null);
                            }),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('All branches')
                            ->searchable()
                            ->preload()
                            ->options(fn (callable $get): array => $this->branchOptions($get('business_id')))
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('chamber_id', null)),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->placeholder('All customers')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ColdStorageAccess::customerOptions()),
                        Select::make('chamber_id')
                            ->label('Chamber')
                            ->placeholder('All chambers')
                            ->searchable()
                            ->preload()
                            ->options(fn (callable $get): array => $this->chamberOptions($get('business_id'), $get('branch_id'))),
                        DatePicker::make('from')
                            ->label('From')
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        DatePicker::make('to')
                            ->label('To')
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (callable $get) => $get('from'))
                            ->native(false),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'xl' => 3,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $user = ColdStorageAccess::user();
        $selected = $this->filters ?? [];

        return [
            'merchant_id' => ColdStorageAccess::merchantId($user),
            'business_id' => $selected['business_id'] ?? null,
            'branch_id' => $selected['branch_id'] ?? null,
            'customer_id' => $selected['customer_id'] ?? null,
            'chamber_id' => $selected['chamber_id'] ?? null,
            'from' => $selected['from'] ?? null,
            'to' => $selected['to'] ?? null,
            'restrict_branches' => $user instanceof User,
            'branch_ids' => $user instanceof User ? ReportService::assignedBranchIds($user) : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function businessOptions(): array
    {
        $user = ColdStorageAccess::user();
        $merchantId = ColdStorageAccess::merchantId($user);

        if ($merchantId === null) {
            return [];
        }

        $query = Business::query()->withoutTrashed()->where('merchant_id', $merchantId);

        if ($user instanceof User) {
            $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    private function branchOptions(?string $businessId): array
    {
        $user = ColdStorageAccess::user();
        $merchantId = ColdStorageAccess::merchantId($user);

        if ($merchantId === null) {
            return [];
        }

        $query = Branch::query()->withoutTrashed()->where('merchant_id', $merchantId);

        if (filled($businessId)) {
            $query->where('business_id', $businessId);
        }

        if ($user instanceof User) {
            $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    private function chamberOptions(?string $businessId, ?string $branchId): array
    {
        $query = ColdStorageAccess::scope(ColdStorageChamber::query());

        if (filled($businessId)) {
            $query->where('business_id', $businessId);
        }

        if (filled($branchId)) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
