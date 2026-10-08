<?php

namespace App\Filament\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Support\ColdStorageAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Component;

class ColdStorageActions
{
    public static function post(string $serviceClass): Action
    {
        return Action::make('post')
            ->label('Post')
            ->color('success')
            ->visible(fn ($record): bool => ($record->status ?? null) === 'draft' && ColdStorageAccess::can('update'))
            ->requiresConfirmation()
            ->action(function ($record, Component $livewire) use ($serviceClass): void {
                try {
                    app($serviceClass)->post($record, ColdStorageAccess::actorId());
                    Notification::make()->title('Posted')->success()->send();
                    self::redirectToIndex($livewire);
                } catch (ColdStorageException $exception) {
                    Notification::make()->title('Could not post')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function cancel(string $serviceClass): Action
    {
        return Action::make('cancelDocument')
            ->label('Cancel and reverse')
            ->color('danger')
            ->visible(fn ($record): bool => ($record->status ?? null) === 'posted' && ColdStorageAccess::can('update'))
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->rows(3)->columnSpanFull(),
            ])
            ->modalWidth('md')
            ->action(function ($record, array $data, Component $livewire) use ($serviceClass): void {
                try {
                    app($serviceClass)->cancel($record, ColdStorageAccess::actorId(), (string) $data['reason']);
                    Notification::make()->title('Cancelled')->success()->send();
                    self::redirectToIndex($livewire);
                } catch (ColdStorageException $exception) {
                    Notification::make()->title('Could not cancel')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    private static function redirectToIndex(Component $livewire): void
    {
        if (! $livewire instanceof Page) {
            return;
        }

        $livewire->redirect($livewire::getResource()::getUrl('index'));
    }

    public static function print(string $type): Action
    {
        return Action::make('printDocument')
            ->label('Print')
            ->visible(fn ($record): bool => ($record->status ?? null) === 'posted')
            ->url(fn ($record): string => route('cold-storage.print', ['type' => $type, 'id' => $record->getKey()]))
            ->openUrlInNewTab();
    }

    public static function invoice(): Action
    {
        return Action::make('invoiceDocument')
            ->label('Invoice')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn ($record): bool => ($record->status ?? null) === 'posted')
            ->url(fn ($record): string => route('cold-storage.print', ['type' => 'bill', 'id' => $record->getKey()]))
            ->openUrlInNewTab();
    }
}
