<?php

namespace App\Filament\Resources\ColdStorageBills\Pages;

use App\Exceptions\ColdStorageException;
use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Services\ColdStorage\BillingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageBill extends ViewRecord
{
    protected static string $resource = ColdStorageBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Calculation preview')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->action(function (BillingService $billing): void {
                    try {
                        $preview = $billing->preview($this->record);
                        $lines = collect($preview['lines'])->map(fn (array $line): string => $line['lot_number'].': '.$line['quantity_days'].' '.$line['charge_basis'].'-days from '.$line['period_start'].' to '.$line['period_end'].' at '.$line['rate'].' = '.$line['line_total'])->implode("\n");
                        Notification::make()
                            ->title('Storage '.number_format($preview['storage_total'], 2).' + services '.number_format($preview['service_total'], 2).' = '.number_format($preview['total'], 2))
                            ->body($lines !== '' ? $lines : 'No storage quantity in this period.')
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Preview unavailable')->body($exception->getMessage())->danger()->send();
                    }
                }),
            ColdStorageActions::post(BillingService::class),
            ColdStorageActions::print('bill'),
            ColdStorageActions::cancel(BillingService::class),
        ];
    }
}
