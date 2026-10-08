<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Widgets\ColdStorageStatsWidget;
use App\Filament\Widgets\ReportsStatsWidget;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\DemoAccount;
use App\Support\UiModules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'dashboard';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $widgets = [];

        if (UiModules::enabled('cold_storage')) {
            $widgets[] = ColdStorageStatsWidget::class;
        }

        if (UiModules::anyDashboardOverviewEnabled()) {
            $widgets[] = ReportsStatsWidget::class;
        }

        return $widgets;
    }

    public function filtersForm(Schema $schema): Schema
    {
        $fields = [
            Select::make('business_id')
                ->label('Business')
                ->placeholder('All businesses')
                ->searchable()
                ->preload()
                ->options(function () {
                    $user = Filament::auth()->user();

                    $merchantId = match (true) {
                        $user instanceof Merchant => $user->id,
                        $user instanceof User => $user->merchant_id,
                        default => null,
                    };

                    if (! $merchantId) {
                        return [];
                    }

                    $query = Business::query()
                        ->withoutTrashed()
                        ->where('merchant_id', $merchantId);

                    if ($user instanceof User) {
                        $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
                    }

                    return $query
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->live()
                ->afterStateUpdated(function (callable $set): void {
                    $set('branch_id', null);
                    $set('product_variant_ids', []);
                }),

            Select::make('branch_id')
                ->label('Branch')
                ->placeholder('All branches')
                ->searchable()
                ->preload()
                ->live()
                ->options(function (callable $get) {
                    $user = Filament::auth()->user();

                    $merchantId = match (true) {
                        $user instanceof Merchant => $user->id,
                        $user instanceof User => $user->merchant_id,
                        default => null,
                    };

                    if (! $merchantId) {
                        return [];
                    }

                    $query = Branch::query()
                        ->withoutTrashed()
                        ->where('merchant_id', $merchantId);

                    if ($businessId = $get('business_id')) {
                        $query->where('business_id', $businessId);
                    }

                    if ($user instanceof User) {
                        $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
                    }

                    return $query
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->afterStateUpdated(fn (callable $set) => $set('product_variant_ids', [])),
        ];

        if (UiModules::enabled('product_variants') && UiModules::anyDashboardOverviewEnabled()) {
            $fields[] = Select::make('product_variant_ids')
                ->label('Product Variant')
                ->placeholder('All variants')
                ->multiple()
                ->searchable()
                ->preload()
                ->optionsLimit(500)
                ->wrapOptionLabels(false)
                ->extraAttributes([
                    'class' => 'dashboard-product-variant-select',
                ])
                ->extraFieldWrapperAttributes(fn (callable $get): array => [
                    'class' => 'dashboard-product-variant-select',
                    'data-dashboard-option-values' => json_encode(array_map(
                        'strval',
                        array_keys(self::productVariantFilterOptions($get)),
                    )),
                ])
                ->options(fn (callable $get): array => self::productVariantFilterOptions($get))
                ->getOptionLabelsUsing(function (array $values): array {
                    if ($values === []) {
                        return [];
                    }

                    return ProductVariant::query()
                        ->withoutTrashed()
                        ->whereIn('product_variants.id', $values)
                        ->join('products', 'products.id', '=', 'product_variants.product_id')
                        ->whereNull('products.deleted_at')
                        ->select([
                            'product_variants.id',
                            'product_variants.name',
                            'product_variants.sku',
                            'products.name as product_name',
                        ])
                        ->get()
                        ->mapWithKeys(fn (ProductVariant $variant): array => [
                            $variant->id => self::productVariantFilterLabel($variant),
                        ])
                        ->all();
                });
        }

        $fields[] = DatePicker::make('date_from')
            ->label('Date From')
            ->default(fn (): string => DemoAccount::isDemoMerchant()
                ? now()->subDays(30)->toDateString()
                : now()->toDateString())
            ->placeholder('Start date')
            ->displayFormat('d/m/Y')
            ->maxDate(now())
            ->rule('before_or_equal:today')
            ->suffixAction(
                Action::make('clear_date_from')
                    ->icon('heroicon-s-x-mark')
                    ->tooltip('Clear date')
                    ->action(fn (callable $set) => $set('date_from', null))
            )
            ->native(false);

        $fields[] = DatePicker::make('date_to')
            ->label('Date To')
            ->default(now()->toDateString())
            ->placeholder('End date')
            ->displayFormat('d/m/Y')
            ->minDate(fn (callable $get) => $get('date_from'))
            ->maxDate(now())
            ->rule('before_or_equal:today')
            ->rule('after_or_equal:date_from')
            ->suffixAction(
                Action::make('clear_date_to')
                    ->icon('heroicon-s-x-mark')
                    ->tooltip('Clear date')
                    ->action(fn (callable $set) => $set('date_to', null))
            )
            ->native(false);

        $description = UiModules::enabled('cold_storage') && ! UiModules::anyDashboardOverviewEnabled()
            ? 'Filter cold storage metrics by business, branch, and date.'
            : 'Refine dashboard analytics by business, branch, and date.';

        return $schema
            ->columns(1)
            ->components([
                Section::make('Filters')
                    ->description($description)
                    ->extraAttributes(['class' => 'dashboard-filters-section'])
                    ->schema($fields)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'xl' => min(count($fields), 4),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function productVariantFilterOptions(callable $get): array
    {
        $user = Filament::auth()->user();

        $merchantId = match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };

        if (! $merchantId) {
            return [];
        }

        $query = ProductVariant::query()
            ->withoutTrashed()
            ->where('product_variants.merchant_id', $merchantId)
            ->where('product_variants.is_active', true)
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('products.deleted_at')
            ->select([
                'product_variants.id',
                'product_variants.name',
                'product_variants.sku',
                'products.name as product_name',
            ]);

        if ($businessId = $get('business_id')) {
            $query->whereHas('product.branches', fn ($q) => $q->where('branches.business_id', $businessId)
                ->whereNull('branches.deleted_at')
            );
        }

        if ($branchId = $get('branch_id')) {
            $query->whereHas('product.branches', fn ($q) => $q->where('branches.id', $branchId)
                ->whereNull('branches.deleted_at')
            );
        }

        if ($user instanceof User) {
            $query->whereHas('product.branches.users', fn ($q) => $q->where('users.id', $user->id));
            $query->whereHas('product.branches', fn ($q) => $q->whereNull('branches.deleted_at'));
        }

        return $query
            ->orderBy('products.name')
            ->orderBy('product_variants.name')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => self::productVariantFilterLabel($variant),
            ])
            ->all();
    }

    private static function productVariantFilterLabel(ProductVariant $variant): string
    {
        return trim(
            ($variant->product_name ? $variant->product_name.' - ' : '')
            .($variant->name ?: ($variant->sku ?: (string) $variant->id))
            .($variant->sku ? ' ('.$variant->sku.')' : '')
        );
    }
}
