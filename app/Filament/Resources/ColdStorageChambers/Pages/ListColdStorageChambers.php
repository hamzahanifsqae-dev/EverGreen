<?php

namespace App\Filament\Resources\ColdStorageChambers\Pages;

use App\Filament\Resources\ColdStorageChambers\ColdStorageChamberResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageChambers extends ListRecords
{
    protected static string $resource = ColdStorageChamberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
