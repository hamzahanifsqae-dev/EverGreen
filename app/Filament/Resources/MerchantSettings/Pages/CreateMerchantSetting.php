<?php

namespace App\Filament\Resources\MerchantSettings\Pages;

use App\Enums\AttachmentMetaType;
use App\Enums\AttachmentType;
use App\Filament\Resources\MerchantSettings\MerchantSettingResource;
use App\Models\MerchantSetting;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantSetting extends CreateRecord
{
    protected static string $resource = MerchantSettingResource::class;

    protected static ?string $title = 'Merchant Settings';

    /** Holds the processed logo path captured before create (Filament moves file during getState()) */
    private ?string $pendingLogoPath = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('create'),

            Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    public function mount(): void
    {
        if (auth('merchant')->check()) {
            $existing = MerchantSetting::where(
                'merchant_id',
                auth('merchant')->id()
            )->first();

            if ($existing) {
                $this->redirect(
                    static::getResource()::getUrl('edit', [
                        'record' => $existing,
                        'panel' => 'merchant',
                    ])
                );

                return;
            }
        }

        parent::mount();

        if (auth('merchant')->check()) {
            $merchant = auth('merchant')->user();
            $this->form->fill([
                'cash_in_hand' => $merchant->cash_in_hand,
                'cash_in_bank' => $merchant->cash_in_bank,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth('merchant')->check()) {
            $data['merchant_id'] = auth('merchant')->id();
        }

        // Capture the processed logo path here — by this point Filament has already
        // moved the file from livewire-tmp to public/merchants/logos, so $data has
        // the real final path. We unset it so it doesn't hit MerchantSetting::create()
        // (no such column exists in merchant_settings table).
        $this->pendingLogoPath = collect($data['merchant_logo'] ?? null)->first() ?: null;
        unset($data['merchant_logo']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $merchant = auth('merchant')->user();
        if (! $merchant) {
            return;
        }

        /* ── MERCHANT LOGO ── */
        if ($this->pendingLogoPath) {
            $merchant->logo()?->delete();
            $merchant->logo()->create([
                'merchant_id' => $merchant->id,
                'type' => AttachmentType::IMAGE,
                'meta_type' => AttachmentMetaType::MERCHANT_LOGO,
                'photo_url' => $this->pendingLogoPath,
            ]);
        }

        /* ── CASH ACCOUNTS ── */
        $state = $this->form->getRawState();
        if (array_key_exists('cash_in_hand', $state) || array_key_exists('cash_in_bank', $state)) {
            $merchant->update([
                'cash_in_hand' => array_key_exists('cash_in_hand', $state)
                    ? $this->cashAccountAmount($state['cash_in_hand'])
                    : $merchant->cash_in_hand,
                'cash_in_bank' => array_key_exists('cash_in_bank', $state)
                    ? $this->cashAccountAmount($state['cash_in_bank'])
                    : $merchant->cash_in_bank,
            ]);
        }
    }

    private function cashAccountAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
