<?php

namespace App\Filament\Resources\ColdStorageBills\Pages;

use App\Exceptions\ColdStorageException;
use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Services\ColdStorage\BillingService;
use App\Support\ColdStorageAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewColdStorageBill extends ViewRecord
{
    protected static string $resource = ColdStorageBillResource::class;

    protected function getHeaderActions(): array
    {
        $currency = config('cold-storage.currency');

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
            Action::make('recordPayment')
                ->label('Record payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'posted'
                    && (float) $this->record->due_amount > 0
                    && ColdStorageAccess::can('update'))
                ->modalHeading('Record payment received')
                ->modalDescription(fn (): string => 'Outstanding balance: '.$currency.' '.number_format((float) $this->record->due_amount, 2))
                ->schema([
                    TextInput::make('amount')
                        ->label('Amount received')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix($currency)
                        ->default(fn (): string => number_format((float) $this->record->due_amount, 2, '.', '')),
                    DatePicker::make('payment_date')
                        ->label('Payment date')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),
                    Select::make('method')
                        ->label('Method')
                        ->options([
                            'cash' => 'Cash',
                            'bank_transfer' => 'Bank transfer',
                            'cheque' => 'Cheque',
                            'card' => 'Card',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->default('cash'),
                ])
                ->action(function (array $data, BillingService $billing): void {
                    try {
                        $paymentDate = Carbon::parse($data['payment_date'])->toDateString();

                        $billing->recordPayment(
                            $this->record,
                            (float) $data['amount'],
                            $paymentDate,
                            (string) $data['method'],
                            ColdStorageAccess::actorId(),
                        );

                        $this->record->refresh()->load('payments');

                        Notification::make()
                            ->title('Payment recorded')
                            ->body($currency.' '.number_format((float) $data['amount'], 2).' received. Due now '.$currency.' '.number_format((float) $this->record->due_amount, 2).'.')
                            ->success()
                            ->send();
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Could not record payment')->body($exception->getMessage())->danger()->send();
                    }
                }),
            ColdStorageActions::invoice(),
            ColdStorageActions::cancel(BillingService::class),
        ];
    }
}
