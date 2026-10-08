<?php

namespace App\Filament\Resources\ProductVariants;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\ProductVariants\Pages\CreateVariant;
use App\Filament\Resources\ProductVariants\Pages\EditVariant;
use App\Filament\Resources\ProductVariants\Pages\ListVariants;
use App\Filament\Resources\ProductVariants\Schemas\VariantForm;
use App\Filament\Resources\ProductVariants\Tables\VariantsTable;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\ProductVariant;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductVariantResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'product_variants';
    }

    protected static ?string $model = ProductVariant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::SquaresPlus;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        // 🔐 Module gate
        if (! PermissionModule::isEnabledForCurrentMerchant('products_variants')) {
            return false;
        }

        // 🔐 Permission gate
        return $user->hasPermissionTo('products_variants.view', $guard);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $query = parent::getEloquentQuery();

        $merchantId = match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };

        return $merchantId
            ? $query->where('merchant_id', $merchantId)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return VariantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariants::route('/'),
            'create' => CreateVariant::route('/create'),
            'edit' => EditVariant::route('/{record}/edit'),
        ];
    }
}
