<?php

namespace App\Filament\Resources\ColdStorageBills\Pages;

use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageBill extends CreateRecord
{
    protected static string $resource = ColdStorageBillResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
