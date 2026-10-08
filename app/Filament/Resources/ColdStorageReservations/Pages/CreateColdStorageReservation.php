<?php

namespace App\Filament\Resources\ColdStorageReservations\Pages;

use App\Filament\Resources\ColdStorageReservations\ColdStorageReservationResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageReservation extends CreateRecord
{
    protected static string $resource = ColdStorageReservationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
