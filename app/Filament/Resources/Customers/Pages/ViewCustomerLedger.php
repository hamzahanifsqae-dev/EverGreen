<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Exports\ColdStorageCustomerLedgerExport;
use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\ColdStorageBill;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\ColdStorage\BillingService;
use App\Services\ColdStorage\CustomerLedgerService;
use App\Support\ColdStorageAccess;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ViewCustomerLedger extends Page
{
    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.resources.customers.pages.view-customer-ledger';

    public Customer $record;

    public ?string $from = null;

    public ?string $to = null;

    /**
     * @var array<string, mixed>
     */
    public array $statement = [];

    public function mount(Customer $record): void
    {
        abort_unless(
            CustomerResource::scopeVisibleCustomers(
                Customer::query()->whereKey($record->getKey()),
                Filament::auth()->user(),
            )->exists(),
            404,
        );

        $this->record = $record;
        $this->loadStatement();
    }

    public function getTitle(): string
    {
        return "Customer Ledger — {$this->record->name}";
    }

    public function applyFilters(): void
    {
        $this->loadStatement();
    }

    public function clearFilters(): void
    {
        $this->from = null;
        $this->to = null;
        $this->loadStatement();
    }

    private function loadStatement(): void
    {
        $this->statement = app(CustomerLedgerService::class)->statement(
            $this->record,
            ['from' => $this->from, 'to' => $this->to],
            ColdStorageAccess::user(),
        );
    }

    protected function getHeaderActions(): array
    {
        $currency = config('cold-storage.currency');

        return [
            Action::make('back_to_overview')
                ->label('Cold storage overview')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CustomerResource::getUrl('cold-storage', ['record' => $this->record])),

            Action::make('print')
                ->label('Print statement')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('cold-storage.print.ledger', [
                    'customer' => $this->record->id,
                    'from' => $this->from,
                    'to' => $this->to,
                ]))
                ->openUrlInNewTab(),

            Action::make('exportLedger')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => \Maatwebsite\Excel\Facades\Excel::download(
                    new ColdStorageCustomerLedgerExport($this->statement['rows'], $this->statement['opening_balance']),
                    'customer-ledger-'.\Illuminate\Support\Str::slug($this->record->name).'-'.now()->format('Y-m-d_H-i-s').'.xlsx',
                )),

            Action::make('recordAdvance')
                ->label('Record advance')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => ColdStorageAccess::can('update'))
                ->schema([
                    TextInput::make('amount')->label('Amount')->numeric()->required()->minValue(0.01)->prefix($currency),
                    DatePicker::make('payment_date')->label('Date')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                    Select::make('method')
                        ->label('Received into')
                        ->options(['cash_in_hand' => 'Cash in hand', 'cash_in_bank' => 'Bank'])
                        ->required(),
                ])
                ->action(function (array $data, BillingService $billing): void {
                    try {
                        $billing->recordAdvancePayment(
                            $this->record,
                            (float) $data['amount'],
                            $data['payment_date'],
                            (string) $data['method'],
                            ColdStorageAccess::actorId(),
                        );
                        $this->loadStatement();
                        Notification::make()->title('Advance recorded')->success()->send();
                    } catch (\App\Exceptions\ColdStorageException $exception) {
                        Notification::make()->title('Could not record advance')->body($exception->getMessage())->danger()->send();
                    }
                }),

            Action::make('allocateAdvance')
                ->label('Allocate advance to bill')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->visible(fn (): bool => ColdStorageAccess::can('update') && $this->statement['unallocated_advance'] > 0)
                ->schema([
                    Select::make('payment_id')
                        ->label('Unallocated advance')
                        ->options(fn (): array => Payment::query()
                            ->where('party_type', Customer::class)
                            ->where('party_id', $this->record->id)
                            ->where('entry_type', 'advance')
                            ->where('unallocated_amount', '>', 0)
                            ->get()
                            ->mapWithKeys(fn (Payment $p) => [$p->id => $p->payment_date->format('d/m/Y').' — '.$currency.' '.number_format((float) $p->unallocated_amount, 2).' left'])
                            ->all())
                        ->required(),
                    Select::make('bill_id')
                        ->label('Bill')
                        ->options(fn (): array => ColdStorageBill::query()
                            ->where('customer_id', $this->record->id)
                            ->where('status', 'posted')
                            ->where('due_amount', '>', 0)
                            ->get()
                            ->mapWithKeys(fn (ColdStorageBill $b) => [$b->id => $b->bill_no.' — due '.number_format((float) $b->due_amount, 2)])
                            ->all())
                        ->required(),
                    TextInput::make('amount')->label('Amount to apply')->numeric()->required()->minValue(0.01)->prefix($currency),
                    DatePicker::make('allocated_on')->label('Date')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data, BillingService $billing): void {
                    try {
                        $advance = Payment::query()->whereKey($data['payment_id'])->firstOrFail();
                        $bill = ColdStorageBill::query()->whereKey($data['bill_id'])->firstOrFail();

                        $billing->allocateAdvanceToBill($advance, $bill, (float) $data['amount'], $data['allocated_on'], ColdStorageAccess::actorId());
                        $this->loadStatement();
                        Notification::make()->title('Advance applied to bill')->success()->send();
                    } catch (\App\Exceptions\ColdStorageException $exception) {
                        Notification::make()->title('Could not allocate advance')->body($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function billViewUrl(string $id): string
    {
        return ColdStorageBillResource::getUrl('view', ['record' => $id]);
    }
}
