<?php

namespace App\Filament\Resources\ColdStorageTransfers\Pages;

use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageTransfers\ColdStorageTransferResource;
use App\Services\ColdStorage\TransferService;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageTransfer extends ViewRecord
{
    protected static string $resource = ColdStorageTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColdStorageActions::post(TransferService::class),
            ColdStorageActions::cancel(TransferService::class),
        ];
    }
}
