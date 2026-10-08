<?php

namespace App\Filament\Resources\ColdStorageTransfers\Pages;

use App\Filament\Resources\ColdStorageTransfers\ColdStorageTransferResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageTransfer extends CreateRecord
{
    protected static string $resource = ColdStorageTransferResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
