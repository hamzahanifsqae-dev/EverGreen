<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\PermissionModule;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'staff';
    }

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        $guard = Filament::getCurrentPanel()->getAuthGuard();
        //        if (! $user || $guard=='staff') {
        //            return false;
        //        }

        if (! PermissionModule::isEnabledForCurrentMerchant('users')) {
            return false;
        }

        return $user->hasPermissionTo(
            'users.view',
            $guard
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();
        $query = parent::getEloquentQuery();

        // Scope to merchant
        $query->where('merchant_id', $user->merchant_id ?? $user->id);

        // If logged in user is Admin, hide other Admin accounts
        // Admin cannot see the merchant account (merchants are in a different table)
        // But hide users with Admin role from being seen by non-admin staff
        $isAdmin = $user->hasRole('Admin', 'merchant');

        if (! $isAdmin) {
            // Non-admin staff cannot see Admin accounts
            $adminRoleId = \DB::table('roles')
                ->where('name', 'Admin')
                ->where('guard_name', 'merchant')
                ->value('id');

            if ($adminRoleId) {
                $adminUserIds = \DB::table('model_has_roles')
                    ->where('role_id', $adminRoleId)
                    ->where('model_type', 'App\\Models\\User')
                    ->pluck('model_id');

                $query->whereNotIn('id', $adminUserIds);
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
