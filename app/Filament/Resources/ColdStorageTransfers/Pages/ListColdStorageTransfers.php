<?php

namespace App\Filament\Resources\ColdStorageTransfers\Pages;

use App\Filament\Resources\ColdStorageTransfers\ColdStorageTransferResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageTransfers extends ListRecords
{
    protected static string $resource = ColdStorageTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
