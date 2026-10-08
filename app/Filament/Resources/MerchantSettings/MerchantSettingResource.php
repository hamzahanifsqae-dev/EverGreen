<?php

namespace App\Filament\Resources\MerchantSettings;

use App\Filament\Resources\MerchantSettings\Pages\CreateMerchantSetting;
use App\Filament\Resources\MerchantSettings\Pages\EditMerchantSetting;
use App\Filament\Resources\MerchantSettings\Pages\ListMerchantSettings;
use App\Filament\Resources\MerchantSettings\Schemas\MerchantSettingForm;
use App\Filament\Resources\MerchantSettings\Tables\MerchantSettingsTable;
use App\Models\MerchantSetting;
use App\Models\PermissionModule;
use App\Support\UiModules;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantSettingResource extends Resource
{
    protected static ?string $model = MerchantSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog8Tooth;

    //    protected static bool $shouldRegisterNavigation = false;
    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

    protected static ?string $navigationLabel = 'Merchant Settings';

    protected static ?string $modelLabel = 'Merchant Settings';

    protected static ?string $pluralModelLabel = 'Merchant Settings';

    protected static ?string $recordTitleAttribute = 'MerchantSetting';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        // 🔐 Module gate
        if (! PermissionModule::isEnabledForCurrentMerchant('merchant_settings')) {
            return false;
        }

        // 🔐 Permission gate
        return $user->hasPermissionTo('merchant_settings.view', $guard);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiModules::enabled('merchant_settings')
            && Filament::getCurrentPanel()?->getId() === 'merchant';
    }

    public static function form(Schema $schema): Schema
    {
        return MerchantSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantSettingsTable::configure($table);
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
            'index' => ListMerchantSettings::route('/'),
            'create' => CreateMerchantSetting::route('/create'),
            'edit' => EditMerchantSetting::route('/{record}/edit'),
        ];
    }
}
