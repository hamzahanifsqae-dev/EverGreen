<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Models\ColdStorageAlertEmailSetting;
use App\Services\ColdStorage\ActionAlertMailService;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ColdStorageAlertEmails extends Page implements HasForms
{
    use ColdStorageSubmoduleNavigation;
    use InteractsWithForms;

    protected static function coldStorageUiModuleKey(): ?string
    {
        return 'cold_storage_alert_emails';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    protected static ?string $navigationLabel = 'Alert emails';

    protected static ?string $title = 'Action alert emails';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.cold-storage-alert-emails';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return UiModules::enabled('cold_storage_alert_emails')
            && ColdStorageAccess::can('view');
    }

    public function mount(): void
    {
        $merchantId = ColdStorageAccess::merchantId();

        if ($merchantId === null) {
            $this->data = [
                'recipient_emails' => [],
                'is_enabled' => false,
                'include_temperature' => true,
                'include_bills' => true,
                'include_reservations' => true,
            ];

            return;
        }

        $setting = app(ActionAlertMailService::class)->settingsForMerchant($merchantId);

        $this->data = [
            'recipient_emails' => $setting->normalizedRecipientEmails(),
            'is_enabled' => $setting->is_enabled,
            'include_temperature' => $setting->include_temperature,
            'include_bills' => $setting->include_bills,
            'include_reservations' => $setting->include_reservations,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recipients')
                    ->description('These addresses receive cold storage action-alert digests. They are separate from notification templates and customer emails.')
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TagsInput::make('recipient_emails')
                            ->label('Email addresses')
                            ->placeholder('Add an email and press Enter')
                            ->helperText('Only valid emails are kept. Example: ops@yourcoldstore.com')
                            ->reorderable()
                            ->columnSpanFull(),
                        Toggle::make('is_enabled')
                            ->label('Send daily digest emails')
                            ->helperText('When enabled, open alerts are emailed once per day to the list above.'),
                    ]),
                Section::make('Include in digest')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('include_temperature')->label('Temperature exceptions'),
                        Toggle::make('include_bills')->label('Bills with balance'),
                        Toggle::make('include_reservations')->label('Upcoming reservations'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->visible(fn (): bool => ColdStorageAccess::can('update') && ColdStorageAccess::merchantId() !== null)
                ->action(fn () => $this->save()),
            Action::make('sendNow')
                ->label('Send digest now')
                ->color('gray')
                ->visible(fn (): bool => ColdStorageAccess::can('update') && ColdStorageAccess::merchantId() !== null)
                ->requiresConfirmation()
                ->modalHeading('Send action alert digest now?')
                ->modalDescription('Sends the current open alerts to the saved recipient list (even if daily sending is off).')
                ->action(function (ActionAlertMailService $mailer): void {
                    $this->save(quiet: true);

                    $merchantId = ColdStorageAccess::merchantId();

                    if ($merchantId === null) {
                        return;
                    }

                    $result = $mailer->sendDigestForMerchant($merchantId, force: true);

                    if (str_starts_with($result, 'SENT')) {
                        Notification::make()->title('Digest sent')->body($result)->success()->send();
                    } elseif (str_starts_with($result, 'SKIPPED')) {
                        Notification::make()->title('Nothing sent')->body($result)->warning()->send();
                    } else {
                        Notification::make()->title('Send failed')->body($result)->danger()->send();
                    }
                }),
        ];
    }

    public function save(bool $quiet = false): void
    {
        if (! ColdStorageAccess::can('update')) {
            Notification::make()->title('You cannot update alert emails')->danger()->send();

            return;
        }

        $merchantId = ColdStorageAccess::merchantId();

        if ($merchantId === null) {
            Notification::make()->title('No merchant context')->danger()->send();

            return;
        }

        $data = $this->form->getState();

        $setting = app(ActionAlertMailService::class)->settingsForMerchant($merchantId);
        $setting->update([
            'recipient_emails' => collect($data['recipient_emails'] ?? [])
                ->map(fn (mixed $email): string => strtolower(trim((string) $email)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'include_temperature' => (bool) ($data['include_temperature'] ?? true),
            'include_bills' => (bool) ($data['include_bills'] ?? true),
            'include_reservations' => (bool) ($data['include_reservations'] ?? true),
        ]);

        if (! $quiet) {
            Notification::make()->title('Alert email settings saved')->success()->send();
        }
    }

    public function lastSentLabel(): ?string
    {
        $merchantId = ColdStorageAccess::merchantId();

        if ($merchantId === null) {
            return null;
        }

        $lastSent = ColdStorageAlertEmailSetting::query()
            ->where('merchant_id', $merchantId)
            ->value('last_sent_at');

        return $lastSent
            ? Carbon::parse($lastSent)->timezone(config('app.timezone'))->format(config('cold-storage.date_format').' H:i')
            : null;
    }
}
