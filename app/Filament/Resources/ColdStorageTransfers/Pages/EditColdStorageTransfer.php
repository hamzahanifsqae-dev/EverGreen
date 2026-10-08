<?php

namespace App\Filament\Resources\ColdStorageTransfers\Pages;

use App\Filament\Resources\ColdStorageTransfers\ColdStorageTransferResource;
use App\Models\Branch;
use Filament\Resources\Pages\EditRecord;

class EditColdStorageTransfer extends EditRecord
{
    protected static string $resource = ColdStorageTransferResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['branch_id'] ?? null)) {
            $data['business_id'] = Branch::query()->whereKey($data['branch_id'])->value('business_id');
        }

        return $data;
    }
}
