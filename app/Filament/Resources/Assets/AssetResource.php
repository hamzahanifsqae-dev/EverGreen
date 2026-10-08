<?php

namespace App\Filament\Resources\Assets;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
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

class AssetResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'assets';
    }

    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cube;

    protected static string|\UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Company Assets';

    protected static ?string $modelLabel = 'Asset';

    protected static ?string $pluralModelLabel = 'Assets';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('assets')) {
            return false;
        }

        return $user->hasPermissionTo('assets.view', $guard);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        $merchantId = match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };

        if (! $merchantId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('merchant_id', $merchantId);

        if ($user instanceof Merchant) {
            return $query;
        }

        if ($user instanceof User) {
            return $query
                ->whereHas('business.users', fn (Builder $q) => $q->where('users.id', $user->id))
                ->whereHas('branch.users', fn (Builder $q) => $q->where('users.id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'view' => ViewAsset::route('/{record}'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }
}
