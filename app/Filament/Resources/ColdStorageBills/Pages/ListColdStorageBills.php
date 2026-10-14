<?php

namespace App\Filament\Resources\ColdStorageBills\Pages;

use App\Enums\ColdStorageChargeBasis;
use App\Enums\ColdStorageChargePeriod;
use App\Exceptions\ColdStorageException;
use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Models\ColdStorageDispatch;
use App\Services\ColdStorage\BillingService;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;

class ListColdStorageBills extends ListRecords
{
    protected static string $resource = ColdStorageBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('billFromStock')
                ->label('Bill from stock')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->modalHeading('Create storage bill from stock')
                ->modalDescription('Creates a draft bill for the customer and period. Open it to preview or post.')
                ->modalWidth('lg')
                ->visible(fn (): bool => UiModules::enabled('cold_storage_quick_bill') && ColdStorageAccess::can('create'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn (): array => ColdStorageAccess::branchOptions())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->options(fn (): array => ColdStorageAccess::customerOptions())
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('period_start')
                            ->label('Period start')
                            ->required()
                            ->default(now()->startOfMonth())
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        DatePicker::make('period_end')
                            ->label('Period end')
                            ->required()
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->minDate(fn (callable $get) => $get('period_start')),
                        Select::make('charge_basis')
                            ->label('Charge basis')
                            ->options(ColdStorageChargeBasis::options())
                            ->required()
                            ->default(ColdStorageChargeBasis::Bag->value),
                        Select::make('charge_period')
                            ->label('Charge period')
                            ->options(ColdStorageChargePeriod::options())
                            ->required()
                            ->default(ColdStorageChargePeriod::Daily->value),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data, BillingService $billing): void {
                    try {
                        $bill = $billing->createDraftFromStock($data, ColdStorageAccess::actorId());
                        $preview = $billing->preview($bill);

                        Notification::make()
                            ->title('Draft bill created')
                            ->body(
                                $preview['lines'] === []
                                    ? 'Open the bill to review — no storage quantity was found for this period.'
                                    : 'Estimated total '.number_format($preview['total'], 2).' '.config('cold-storage.currency')
                            )
                            ->success()
                            ->send();

                        $this->redirect(ColdStorageBillResource::getUrl('view', ['record' => $bill]));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Could not create bill')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('billWithdrawal')
                ->label('Bill a withdrawal')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->modalHeading('Bill a returned withdrawal')
                ->modalDescription('Creates a draft bill scoped to one withdrawal\'s lots only — charges exactly what was returned, even if it left the same day it arrived, not the customer\'s full stock.')
                ->modalWidth('lg')
                ->visible(fn (): bool => ColdStorageAccess::can('create'))
                ->schema([
                    Select::make('dispatch_id')
                        ->label('Withdrawal')
                        ->options(fn (): array => ColdStorageAccess::scope(ColdStorageDispatch::query())
                            ->where('status', 'posted')
                            ->whereNotExists(fn ($query) => $query
                                ->selectRaw('1')
                                ->from('cold_storage_bills')
                                ->whereColumn('cold_storage_bills.dispatch_id', 'cold_storage_dispatches.id'))
                            ->with('customer')
                            ->latest('dispatched_on')
                            ->get()
                            ->mapWithKeys(fn (ColdStorageDispatch $dispatch): array => [
                                $dispatch->id => $dispatch->dispatch_no.' — '.($dispatch->customer?->name ?? '—').' ('.$dispatch->dispatched_on->format('d/m/Y').')',
                            ])
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Only posted withdrawals that don\'t already have a bill are listed.'),
                ])
                ->action(function (array $data, BillingService $billing): void {
                    try {
                        $dispatch = ColdStorageAccess::scope(ColdStorageDispatch::query())->findOrFail($data['dispatch_id']);
                        $bill = $billing->createDraftFromDispatch($dispatch, ColdStorageAccess::actorId());
                        $preview = $billing->preview($bill);

                        Notification::make()
                            ->title('Draft bill created for this withdrawal')
                            ->body(
                                $preview['lines'] === []
                                    ? 'Open the bill to review — no billable quantity was found.'
                                    : 'Estimated total '.number_format($preview['total'], 2).' '.config('cold-storage.currency')
                            )
                            ->success()
                            ->send();

                        $this->redirect(ColdStorageBillResource::getUrl('view', ['record' => $bill]));
                    } catch (ColdStorageException $exception) {
                        Notification::make()->title('Could not create bill')->body($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
