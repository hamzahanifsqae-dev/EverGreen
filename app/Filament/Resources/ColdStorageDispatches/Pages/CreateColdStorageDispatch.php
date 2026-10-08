<?php

namespace App\Filament\Resources\ColdStorageDispatches\Pages;

use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageDispatch extends CreateRecord
{
    protected static string $resource = ColdStorageDispatchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
