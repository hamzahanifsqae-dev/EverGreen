<?php

namespace App\Filament\Resources\ColdStorageAdjustments\Pages;

use App\Filament\Resources\ColdStorageAdjustments\ColdStorageAdjustmentResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageAdjustment extends CreateRecord
{
    protected static string $resource = ColdStorageAdjustmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
