<?php

namespace App\Filament\Resources\ColdStorageAdjustments\Pages;

use App\Filament\Resources\ColdStorageAdjustments\ColdStorageAdjustmentResource;
use App\Models\Branch;
use Filament\Resources\Pages\EditRecord;

class EditColdStorageAdjustment extends EditRecord
{
    protected static string $resource = ColdStorageAdjustmentResource::class;

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
