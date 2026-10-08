<?php

namespace App\Filament\Resources\ColdStorageReceipts\Pages;

use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageReceipts\ColdStorageReceiptResource;
use App\Services\ColdStorage\ReceiptService;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageReceipt extends ViewRecord
{
    protected static string $resource = ColdStorageReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColdStorageActions::post(ReceiptService::class),
            ColdStorageActions::print('receipt'),
            ColdStorageActions::cancel(ReceiptService::class),
        ];
    }
}
