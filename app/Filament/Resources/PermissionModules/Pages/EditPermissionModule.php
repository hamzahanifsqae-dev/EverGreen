<?php

namespace App\Filament\Resources\PermissionModules\Pages;

use App\Filament\Resources\PermissionModules\PermissionModuleResource;
use App\Models\Permission;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPermissionModule extends EditRecord
{
    protected static string $resource = PermissionModuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->action(function () {
                    $module = $this->record->module;

                    DB::transaction(function () use ($module) {
                        // 🔥 delete permissions first
                        Permission::where('name', 'like', "{$module}.%")->delete();

                        // 🔥 delete module
                        $this->record->delete();
                    });

                    $this->redirect(
                        PermissionModuleResource::getUrl('index')
                    );
                }),
        ];
    }
}
