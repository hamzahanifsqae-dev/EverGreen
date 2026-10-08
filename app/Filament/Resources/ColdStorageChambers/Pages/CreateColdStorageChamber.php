<?php

namespace App\Filament\Resources\ColdStorageChambers\Pages;

use App\Filament\Resources\ColdStorageChambers\ColdStorageChamberResource;
use App\Models\Branch;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageChamber extends CreateRecord
{
    protected static string $resource = ColdStorageChamberResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['merchant_id'] = ColdStorageAccess::merchantId();
        $data['business_id'] = Branch::query()->whereKey($data['branch_id'])->value('business_id');

        return $data;
    }
}
