<?php

namespace App\Filament\Resources\AssetTypes;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\AssetTypes\Pages\CreateAssetType;
use App\Filament\Resources\AssetTypes\Pages\EditAssetType;
use App\Filament\Resources\AssetTypes\Pages\ListAssetTypes;
use App\Filament\Resources\AssetTypes\Schemas\AssetTypeForm;
use App\Filament\Resources\AssetTypes\Tables\AssetTypesTable;
use App\Models\AssetType;
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

class AssetTypeResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'asset_types';
    }

    protected static ?string $model = AssetType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static string|\UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Asset Types';

    protected static ?string $modelLabel = 'Asset Type';

    protected static ?string $pluralModelLabel = 'Asset Types';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('asset_types')) {
            return false;
        }

        return $user->hasPermissionTo('asset_types.view', $guard);
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

        if (! $merchantId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('merchant_id', $merchantId);
    }

    public static function form(Schema $schema): Schema
    {
        return AssetTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetTypes::route('/'),
            'create' => CreateAssetType::route('/create'),
            'edit' => EditAssetType::route('/{record}/edit'),
        ];
    }
}
