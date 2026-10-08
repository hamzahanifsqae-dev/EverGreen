<?php

namespace App\Filament\Resources\ColdStorageAdjustments\Pages;

use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageAdjustments\ColdStorageAdjustmentResource;
use App\Services\ColdStorage\AdjustmentService;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageAdjustment extends ViewRecord
{
    protected static string $resource = ColdStorageAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColdStorageActions::post(AdjustmentService::class),
            ColdStorageActions::cancel(AdjustmentService::class),
        ];
    }
}
