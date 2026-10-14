<?php

namespace App\Filament\Resources\ColdStorageDispatches\Pages;

use App\Exceptions\ColdStorageException;
use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Models\ColdStorageBill;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\DispatchService;
use App\Support\ColdStorageAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageDispatch extends ViewRecord
{
    protected static string $resource = ColdStorageDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColdStorageActions::post(DispatchService::class),
            ColdStorageActions::print('dispatch'),
            $this->billThisWithdrawal(),
            ColdStorageActions::cancel(DispatchService::class),
        ];
    }

    private function billThisWithdrawal(): Action
    {
        return Action::make('billThisWithdrawal')
            ->label('Bill this withdrawal')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->visible(fn (): bool => $this->record->status === 'posted'
                && ColdStorageAccess::can('create')
                && ! ColdStorageBill::query()->where('dispatch_id', $this->record->id)->exists())
            ->requiresConfirmation()
            ->modalDescription('This creates a draft storage bill scoped to this withdrawal\'s lots only, not the customer\'s full stock. You can still preview and adjust it before posting.')
            ->action(function (BillingService $billing): void {
                try {
                    $bill = $billing->createDraftFromDispatch($this->record, ColdStorageAccess::actorId());
                    Notification::make()->title('Draft bill created for this withdrawal')->success()->send();
                    $this->redirect(ColdStorageBillResource::getUrl('view', ['record' => $bill]));
                } catch (ColdStorageException $exception) {
                    Notification::make()->title('Could not create bill')->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
