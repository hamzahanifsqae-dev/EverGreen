<?php

namespace App\Filament\Resources\ColdStorageRateCards\Pages;

use App\Filament\Resources\ColdStorageRateCards\ColdStorageRateCardResource;
use App\Models\Branch;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateColdStorageRateCard extends CreateRecord
{
    protected static string $resource = ColdStorageRateCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['merchant_id'] = ColdStorageAccess::merchantId();
        $data['created_by'] = ColdStorageAccess::actorId();

        if (filled($data['branch_id'] ?? null)) {
            $data['business_id'] = Branch::query()
                ->whereKey($data['branch_id'])
                ->value('business_id');
        }

        return $data;
    }
}
