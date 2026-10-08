<?php

namespace App\Filament\Resources\ColdStorageTemperatureReadings\Pages;

use App\Filament\Resources\ColdStorageTemperatureReadings\ColdStorageTemperatureReadingResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageTemperatureReadings extends ListRecords
{
    protected static string $resource = ColdStorageTemperatureReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
