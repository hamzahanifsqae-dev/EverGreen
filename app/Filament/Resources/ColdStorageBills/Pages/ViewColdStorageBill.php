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
                ->modalDescription(function () use ($currency): string {
                    $this->record->refresh();

                    return 'Outstanding balance: '.$currency.' '.number_format((float) $this->record->due_amount, 2);
                })
                ->fillForm(function (): array {
                    $this->record->refresh();

                    return [
                        'amount' => number_format((float) $this->record->due_amount, 2, '.', ''),
                        'payment_date' => now()->toDateString(),
                        'method' => 'cash_in_hand',
                    ];
                })
                ->schema([
                    TextInput::make('amount')
                        ->label('Amount received')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $due = (float) $this->record->fresh()?->due_amount;

                                if ((float) $value - $due > 0.009) {
                                    $fail('Amount cannot exceed the outstanding balance of '.config('cold-storage.currency').' '.number_format($due, 2).'.');
                                }
                            };
                        })
                        ->prefix($currency),
                    DatePicker::make('payment_date')
                        ->label('Payment date')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Select::make('method')
                        ->label('Received into')
                        ->options([
                            'cash_in_hand' => 'Cash in hand',
                            'cash_in_bank' => 'Bank',
                        ])
                        ->required()
                        ->helperText('This amount is added to the selected cash account.'),
                ])
                ->action(function (array $data, BillingService $billing) use ($currency): void {
                    try {
                        $this->record->refresh();

                        if ((float) $this->record->due_amount <= 0) {
                            throw ColdStorageException::make('This bill is already fully paid.');
                        }

                        $paymentDate = Carbon::parse($data['payment_date'])->toDateString();
                        $account = $billing->cashAccountForMethod((string) $data['method']);
                        $accountLabel = $account === 'cash_in_bank' ? 'Bank' : 'Cash in hand';

                        $billing->recordPayment(
                            $this->record,
                            (float) $data['amount'],
                            $paymentDate,
                            $account,
                            ColdStorageAccess::actorId(),
                        );

                        $this->record->refresh()->load(['payments', 'lines']);

                        Notification::make()
                            ->title('Payment recorded')
                            ->body($currency.' '.number_format((float) $data['amount'], 2).' added to '.$accountLabel.'. Due now '.$currency.' '.number_format((float) $this->record->due_amount, 2).'.')
                            ->success()
                            ->send();

                        $this->redirect(ColdStorageBillResource::getUrl('view', ['record' => $this->record]));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Could not record payment')->body($exception->getMessage())->danger()->send();
                        $this->halt();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Could not record payment')->body($exception->getMessage())->danger()->send();
                        $this->halt();
                    }
                }),
            ColdStorageActions::invoice(),
            ColdStorageActions::cancel(BillingService::class),
        ];
    }
}
