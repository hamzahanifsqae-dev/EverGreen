<?php

namespace App\Filament\Resources\ColdStorageAdjustments\Pages;

use App\Filament\Resources\ColdStorageAdjustments\ColdStorageAdjustmentResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageAdjustments extends ListRecords
{
    protected static string $resource = ColdStorageAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
