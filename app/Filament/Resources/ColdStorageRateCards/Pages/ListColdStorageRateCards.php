<?php

namespace App\Filament\Resources\ColdStorageRateCards\Pages;

use App\Filament\Resources\ColdStorageRateCards\ColdStorageRateCardResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageRateCards extends ListRecords
{
    protected static string $resource = ColdStorageRateCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
