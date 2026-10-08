<?php

namespace App\Filament\Resources\ColdStorageReservations\Pages;

use App\Exceptions\ColdStorageException;
use App\Filament\Resources\ColdStorageReservations\ColdStorageReservationResource;
use App\Models\ColdStorageReceipt;
use App\Services\ColdStorage\ReservationService;
use App\Support\ColdStorageAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewColdStorageReservation extends ViewRecord
{
    protected static string $resource = ColdStorageReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm')
                ->color('success')
                ->visible(fn (): bool => $this->record->isDraft() && ColdStorageAccess::can('update'))
                ->requiresConfirmation()
                ->action(function (ReservationService $service): void {
                    try {
                        $service->confirm($this->record, ColdStorageAccess::actorId());
                        Notification::make()->title('Reservation confirmed')->success()->send();
                        $this->redirect(ColdStorageReservationResource::getUrl('index'));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Unable to confirm')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('fulfill')
                ->label('Mark fulfilled')
                ->visible(fn (): bool => $this->record->isConfirmed() && ColdStorageAccess::can('update'))
                ->modalWidth('md')
                ->schema([
                    Select::make('receipt_id')
                        ->label('Goods receipt')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => ColdStorageReceipt::query()
                            ->where('merchant_id', $this->record->merchant_id)
                            ->where('branch_id', $this->record->branch_id)
                            ->where('customer_id', $this->record->customer_id)
                            ->where('status', 'posted')
                            ->orderByDesc('received_on')
                            ->pluck('receipt_no', 'id')
                            ->all()),
                ])
                ->action(function (array $data, ReservationService $service): void {
                    try {
                        $service->fulfill($this->record, $data['receipt_id'] ?? null, ColdStorageAccess::actorId());
                        Notification::make()->title('Reservation fulfilled')->success()->send();
                        $this->redirect(ColdStorageReservationResource::getUrl('index'));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Unable to fulfill')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('cancel')
                ->label('Cancel reservation')
                ->color('danger')
                ->visible(fn (): bool => ($this->record->isDraft() || $this->record->isConfirmed()) && ColdStorageAccess::can('update'))
                ->modalWidth('md')
                ->schema([
                    Textarea::make('reason')->label('Reason')->required()->rows(3)->columnSpanFull(),
                ])
                ->action(function (array $data, ReservationService $service): void {
                    try {
                        $service->cancel($this->record, ColdStorageAccess::actorId(), $data['reason']);
                        Notification::make()->title('Reservation cancelled')->success()->send();
                        $this->redirect(ColdStorageReservationResource::getUrl('index'));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Unable to cancel')->body($exception->getMessage())->danger()->send();
                    }
                }),
            EditAction::make()
                ->visible(fn (): bool => $this->record->isDraft() && ColdStorageAccess::can('update')),
        ];
    }
}
