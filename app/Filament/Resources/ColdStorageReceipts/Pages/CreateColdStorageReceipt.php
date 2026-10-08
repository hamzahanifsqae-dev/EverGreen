<?php

namespace App\Filament\Resources\ColdStorageReceipts\Pages;

use App\Filament\Resources\ColdStorageReceipts\ColdStorageReceiptResource;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageReceipt extends CreateRecord
{
    protected static string $resource = ColdStorageReceiptResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColdStorageAccess::stampDocument($data);
    }
}
