<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\Roles\Pages\CreateRoles;
use App\Filament\Resources\Roles\Pages\EditRoles;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RolesForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\PermissionModule;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'roles';
    }

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        $guard = Filament::getCurrentPanel()->getAuthGuard();
        //        if (! $user || $guard=='staff') {
        //            return false;
        //        }

        if (! PermissionModule::isEnabledForCurrentMerchant('roles_permissions')) {
            return false;
        }

        return $user->hasPermissionTo(
            'roles_permissions.view',
            $guard
        );
    }

    public static function form(Schema $schema): Schema
    {
        return RolesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return Role::query()->where('guard_name', 'merchant');
    }

    public static function afterCreate($record, array $data): void
    {
        static::syncPermissions($record, $data);
    }

    public static function afterUpdate($record, array $data): void
    {
        static::syncPermissions($record, $data);
    }

    public static function getAssignablePermissionMatrix(): array
    {
        $enabledModules = PermissionModule::enabledForCurrentMerchant();
        $actions = ['view', 'create', 'update', 'delete'];
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        // Staff can only assign permissions they already have.
        if ($user instanceof User) {
            $matrix = [];

            foreach ($enabledModules as $module) {
                $allowedActions = [];

                foreach ($actions as $action) {
                    if ($user->hasPermissionTo("{$module}.{$action}", $guard)) {
                        $allowedActions[] = $action;
                    }
                }

                if (! empty($allowedActions)) {
                    $matrix[$module] = $allowedActions;
                }
            }

            return $matrix;
        }

        // Merchant/Admin can assign all enabled module actions.
        return collect($enabledModules)
            ->mapWithKeys(fn (string $module) => [$module => $actions])
            ->toArray();
    }

    protected static function syncPermissions($record, array $data)
    {
        $assignable = static::getAssignablePermissionMatrix();
        $permissions = [];

        foreach ($data ?? [] as $module => $actions) {
            if (! array_key_exists($module, $assignable) || ! is_array($actions)) {
                continue;
            }

            foreach ($actions as $action => $checked) {
                if (
                    $checked
                    && $action !== 'select_all'
                    && in_array($action, $assignable[$module], true)
                ) {
                    $permissions[] = "{$module}.{$action}";
                }
            }
        }

        $user = Filament::auth()->user();

        // Preserve non-assignable permissions when staff edits an existing role.
        if ($user instanceof User) {
            $assignablePermissionNames = collect($assignable)
                ->flatMap(fn (array $actions, string $module) => collect($actions)->map(fn (string $action) => "{$module}.{$action}")
                )
                ->all();

            $lockedExistingPermissions = $record->permissions
                ->pluck('name')
                ->reject(fn (string $name) => in_array($name, $assignablePermissionNames, true))
                ->values()
                ->all();

            $permissions = array_values(array_unique(array_merge($lockedExistingPermissions, $permissions)));
        }

        $record->syncPermissions($permissions);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRoles::route('/create'),
            'edit' => EditRoles::route('/{record}/edit'),
        ];
    }
}
