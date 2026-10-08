<?php

namespace App\Filament\Resources\PermissionModules;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\PermissionModules\Pages\CreatePermissionModule;
use App\Filament\Resources\PermissionModules\Pages\EditPermissionModule;
use App\Filament\Resources\PermissionModules\Pages\ListPermissionModules;
use App\Filament\Resources\PermissionModules\Schemas\PermissionModuleForm;
use App\Filament\Resources\PermissionModules\Tables\PermissionModulesTable;
use App\Models\PermissionModule;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermissionModuleResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'permission_modules';
    }

    protected static ?string $model = PermissionModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'PermissionModule';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('merchants.view', $guard)
            || $user->hasPermissionTo('merchants.update', $guard);
    }

    public static function form(Schema $schema): Schema
    {
        return PermissionModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionModulesTable::configure($table);
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
            'index' => ListPermissionModules::route('/'),
            'create' => CreatePermissionModule::route('/create'),
            'edit' => EditPermissionModule::route('/{record}/edit'),
        ];
    }
}
