<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\ColdStorageDispatches\ColdStorageDispatchResource;
use App\Filament\Resources\ColdStorageReceipts\ColdStorageReceiptResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Services\ColdStorage\CustomerStorageOverviewService;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;

class ViewCustomerColdStorage extends Page
{
    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.resources.customers.pages.view-customer-cold-storage';

    public Customer $record;

    /**
     * @var array<string, mixed>
     */
    public array $overview = [];

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
        $this->overview = app(CustomerStorageOverviewService::class)->forCustomer($record);
    }

    public function getTitle(): string
    {
        return "Cold storage — {$this->record->name}";
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('edit_customer')
                ->label('Edit customer')
                ->icon('heroicon-o-pencil-square')
                ->url(CustomerResource::getUrl('edit', ['record' => $this->record])),
        ];

        if (UiModules::enabled('cold_storage') && ColdStorageAccess::can('create')) {
            $actions[] = Action::make('new_receipt')
                ->label('New goods receipt')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(ColdStorageReceiptResource::getUrl('create'));

            $actions[] = Action::make('new_return')
                ->label('New return')
                ->icon('heroicon-o-truck')
                ->url(ColdStorageDispatchResource::getUrl('create'));
        }

        if (UiModules::enabled('sales')) {
            $actions[] = Action::make('sales')
                ->label('Sales')
                ->icon('heroicon-o-shopping-bag')
                ->url(CustomerResource::getUrl('sales', ['record' => $this->record]));
        }

        return $actions;
    }

    public function receiptsIndexUrl(): string
    {
        return $this->filteredResourceUrl(ColdStorageReceiptResource::getUrl('index'));
    }

    public function returnsIndexUrl(): string
    {
        return $this->filteredResourceUrl(ColdStorageDispatchResource::getUrl('index'));
    }

    public function receiptViewUrl(string $id): string
    {
        return ColdStorageReceiptResource::getUrl('view', ['record' => $id]);
    }

    public function returnViewUrl(string $id): string
    {
        return ColdStorageDispatchResource::getUrl('view', ['record' => $id]);
    }

    private function filteredResourceUrl(string $baseUrl): string
    {
        $query = http_build_query([
            'tableFilters' => [
                'customer_id' => [
                    'value' => $this->record->getKey(),
                ],
            ],
        ]);

        return $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').$query;
    }
}
