<?php

namespace App\Filament\Resources\ColdStorageReservations\Pages;

use App\Filament\Resources\ColdStorageReservations\ColdStorageReservationResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageReservations extends ListRecords
{
    protected static string $resource = ColdStorageReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
