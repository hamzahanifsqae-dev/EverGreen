<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Filament\Exports\ColdStorageStockRegisterExport;
use App\Models\Branch;
use App\Models\User;
use App\Services\ColdStorage\StockRegisterService;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

class ColdStorageStockRegister extends Page
{
    use ColdStorageSubmoduleNavigation;
    use HasFiltersForm;

    protected static function coldStorageUiModuleKey(): ?string
    {
        return 'cold_storage_stock_register';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    protected static ?string $navigationLabel = 'Stock register';

    protected static ?string $title = 'Stock register';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.cold-storage-stock-register';

    public static function canAccess(): bool
    {
        return UiModules::enabled('cold_storage_stock_register') && ColdStorageAccess::can('view');
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
                    ->schema([
                        \Filament\Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('All branches')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->branchOptions()),
                        \Filament\Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->placeholder('All customers')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ColdStorageAccess::customerOptions()),
                        TextInput::make('lot_number')->label('Lot number')->placeholder('Any lot'),
                        DatePicker::make('from')->label('From')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('to')->label('To')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 5])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array{groups: list<array<string, mixed>>}
     */
    public function register(): array
    {
        return app(StockRegisterService::class)->register($this->filters(), ColdStorageAccess::user());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $selected = $this->filters ?? [];

        return [
            'merchant_id' => ColdStorageAccess::merchantId(),
            'branch_id' => $selected['branch_id'] ?? null,
            'customer_id' => $selected['customer_id'] ?? null,
            'lot_number' => $selected['lot_number'] ?? null,
            'from' => $selected['from'] ?? null,
            'to' => $selected['to'] ?? null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('cold-storage.print.stock-register', $this->filters()))
                ->openUrlInNewTab(),

            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new ColdStorageStockRegisterExport($this->register()['groups']),
                    'stock-register-'.now()->format('Y-m-d_H-i-s').'.xlsx',
                )),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function branchOptions(): array
    {
        $user = ColdStorageAccess::user();
        $merchantId = ColdStorageAccess::merchantId($user);

        if ($merchantId === null) {
            return [];
        }

        $query = Branch::query()->withoutTrashed()->where('merchant_id', $merchantId);

        if ($user instanceof User) {
            $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id));
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
