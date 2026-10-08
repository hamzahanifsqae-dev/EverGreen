<?php

namespace App\Filament\Resources\ColdStorageReceipts\Pages;

use App\Filament\Resources\ColdStorageReceipts\ColdStorageReceiptResource;
use App\Support\ColdStorageAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColdStorageReceipts extends ListRecords
{
    protected static string $resource = ColdStorageReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => ColdStorageAccess::can('create')),
        ];
    }
}
