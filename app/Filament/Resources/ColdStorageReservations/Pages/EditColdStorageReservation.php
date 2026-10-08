<?php

namespace App\Filament\Resources\ColdStorageReservations\Pages;

use App\Filament\Resources\ColdStorageReservations\ColdStorageReservationResource;
use Filament\Resources\Pages\EditRecord;

class EditColdStorageReservation extends EditRecord
{
    protected static string $resource = ColdStorageReservationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
