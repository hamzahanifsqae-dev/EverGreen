<?php

namespace App\Filament\Resources\ColdStorageReceipts\Pages;

use App\Filament\Resources\ColdStorageReceipts\ColdStorageReceiptResource;
use App\Models\Branch;
use Filament\Resources\Pages\EditRecord;

class EditColdStorageReceipt extends EditRecord
{
    protected static string $resource = ColdStorageReceiptResource::class;

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
