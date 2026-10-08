<?php

namespace App\Filament\Resources\ColdStorageDispatches\Pages;

use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageDispatches extends ListRecords
{
    protected static string $resource = ColdStorageDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
