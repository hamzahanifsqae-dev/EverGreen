<?php

namespace App\Filament\Resources\ColdStorageDispatches\Pages;

use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Models\Branch;
use Filament\Resources\Pages\EditRecord;

class EditColdStorageDispatch extends EditRecord
{
    protected static string $resource = ColdStorageDispatchResource::class;

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
