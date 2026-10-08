<?php

namespace App\Filament\Resources\ColdStorageDispatches\Pages;

use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Services\ColdStorage\DispatchService;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageDispatch extends ViewRecord
{
    protected static string $resource = ColdStorageDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColdStorageActions::post(DispatchService::class),
            ColdStorageActions::print('dispatch'),
            ColdStorageActions::cancel(DispatchService::class),
        ];
    }
}
