<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\ColdStorageBill;
use App\Models\Sale;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected array $branchIds = [];

    public function getTitle(): string
    {
        $name = (string) ($this->record?->name ?? '');

        return 'Edit '.Str::limit($name, 30);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['branch_ids'] = $this->record->branches()
            ->pluck('branches.id')
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->branchIds = array_values($data['branch_ids'] ?? []);
        unset($data['branch_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        CustomerResource::syncCustomerBranches(
            $this->record,
            $this->branchIds,
            Filament::auth()->user(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->color('danger')
                ->before(function (DeleteAction $action) {
                    $record = $this->record;

                    if (! $record) {
                        return;
                    }

                    $hasOutstandingCredit = Sale::query()
                        ->withoutTrashed()
                        ->where('customer_id', $record->id)
                        ->where('due_amount', '>', 0)
                        ->exists();

                    $hasOutstandingStorage = ColdStorageBill::query()
                        ->where('customer_id', $record->id)
                        ->where('status', 'posted')
                        ->where('due_amount', '>', 0)
                        ->exists();

                    if ($hasOutstandingCredit || $hasOutstandingStorage) {
                        Notification::make()
                            ->title('Cannot delete customer')
                            ->body($hasOutstandingStorage
                                ? 'This customer has outstanding storage charges. Clear pending dues first.'
                                : 'This customer has outstanding credit sales. Clear pending dues first.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                })
                ->visible(fn () => auth(Filament::getCurrentPanel()->getAuthGuard())->user()?->hasPermissionTo('customers.delete', Filament::getCurrentPanel()->getAuthGuard())),
        ];
    }
}
