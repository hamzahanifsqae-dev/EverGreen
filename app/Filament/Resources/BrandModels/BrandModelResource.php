<?php

namespace App\Filament\Resources\BrandModels;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\BrandModels\Pages\CreateBrandModel;
use App\Filament\Resources\BrandModels\Pages\EditBrandModel;
use App\Filament\Resources\BrandModels\Pages\ListBrandModels;
use App\Filament\Resources\BrandModels\Schemas\BrandModelForm;
use App\Filament\Resources\BrandModels\Tables\BrandModelsTable;
use App\Models\BrandModel;
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

class BrandModelResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'brand_models';
    }

    protected static ?string $model = BrandModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CubeTransparent;

    protected static ?string $recordTitleAttribute = 'BrandModel';

    protected static ?string $navigationLabel = 'Models';

    protected static ?string $modelLabel = 'Models';

    protected static ?string $pluralModelLabel = 'Models';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();
        if (! $user) {
            return false;
        }

        // 🔐 Module gate
        if (! PermissionModule::isEnabledForCurrentMerchant('models')) {
            return false;
        }

        // 🔐 Permission gate
        return $user->hasPermissionTo('models.view', $guard);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        $merchantId = match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };

        return parent::getEloquentQuery()
            ->when($merchantId, fn ($q) => $q->where('merchant_id', $merchantId));
    }

    public static function form(Schema $schema): Schema
    {
        return BrandModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandModelsTable::configure($table);
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
            'index' => ListBrandModels::route('/'),
            'create' => CreateBrandModel::route('/create'),
            'edit' => EditBrandModel::route('/{record}/edit'),
        ];
    }
}
