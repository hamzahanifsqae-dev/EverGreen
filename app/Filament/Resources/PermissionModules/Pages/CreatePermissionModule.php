<?php

namespace App\Filament\Resources\PermissionModules\Pages;

use App\Filament\Resources\PermissionModules\PermissionModuleResource;
use App\Models\Permission;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePermissionModule extends CreateRecord
{
    protected static string $resource = PermissionModuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $module = $this->record->module;

        $actions = ['view', 'create', 'update', 'delete'];
        $guards = ['admin', 'merchant', 'staff'];

        DB::transaction(function () use ($module, $actions, $guards) {
            foreach ($guards as $guard) {
                foreach ($actions as $action) {
                    Permission::firstOrCreate(
                        [
                            'name' => "{$module}.{$action}",
                            'guard_name' => $guard,
                        ]
                    );
                }
            }
        });
    }
}
