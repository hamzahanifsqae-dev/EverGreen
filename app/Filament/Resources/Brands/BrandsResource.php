<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\Brands\Pages\CreateBrands;
use App\Filament\Resources\Brands\Pages\EditBrands;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Brands\Schemas\BrandsForm;
use App\Filament\Resources\Brands\Tables\BrandsTable;
use App\Models\Brand;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BrandsResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'brands';
    }

    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Swatch;

    protected static ?string $recordTitleAttribute = 'Brand';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        // 🔐 Module gate
        if (! PermissionModule::isEnabledForCurrentMerchant('brands')) {
            return false;
        }

        // 🔐 Permission gate
        return $user->hasPermissionTo('brands.view', $guard);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $query = parent::getEloquentQuery();

        if ($user instanceof Merchant) {
            return $query->where('merchant_id', $user->id);
        }

        if ($user instanceof User) {
            return $query->where('merchant_id', $user->merchant_id);
        }

        return $query->whereRaw('1=0');
    }

    public static function form(Schema $schema): Schema
    {
        return BrandsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrands::route('/create'),
            'edit' => EditBrands::route('/{record}/edit'),
        ];
    }
}
