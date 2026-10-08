<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\ColdStorage\ReportService;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ColdStorageCapacityHeatmap extends Page
{
    use ColdStorageSubmoduleNavigation;
    use HasFiltersForm;

    protected static function coldStorageUiModuleKey(): ?string
    {
        return 'cold_storage_capacity_heatmap';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    protected static ?string $navigationLabel = 'Capacity heatmap';

    protected static ?string $title = 'Capacity heatmap';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.cold-storage-capacity-heatmap';

    public static function canAccess(): bool
    {
        return ColdStorageAccess::can('view');
    }

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Filters')
                    ->description('Limit the capacity heatmap by business and branch.')
                    ->extraAttributes(['class' => 'dashboard-filters-section'])
                    ->schema([
                        Select::make('business_id')
                            ->label('Business')
                            ->placeholder('All businesses')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->businessOptions())
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('branch_id', null)),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('All branches')
                            ->searchable()
                            ->preload()
                            ->options(fn (callable $get): array => $this->branchOptions($get('business_id'))),
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
     * @return list<array<string, mixed>>
     */
    public function heatmapRows(): array
    {
        return app(ReportService::class)->capacityHeatmap($this->filters());
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
}
