<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Support\GeoFormFields;
use App\Models\Merchant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CustomerForm
{
    /**
     * ✅ Reusable components
     * Used by:
     * - Customer Resource (Create/Edit)
     * - Sale inline modal (Create Customer)
     */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->default('+92')
                ->placeholder('+923001234567')
                ->helperText('Enter number with country code, e.g. +923001234567')
                ->regex('/^\+92\d{10}$/')
                ->maxLength(15)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                    $livewire->resetValidation('data.phone');
                    $livewire->resetErrorBag('data.phone');
                }),

            Textarea::make('address')
                ->label('Address')
                ->maxLength(255)
                ->columnSpanFull()
                ->nullable(),

            TextInput::make('email')
                ->label('Email address')
                ->email()
                ->maxLength(255)
                ->unique(
                    Customer::class,
                    'email',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->withoutTrashed()
                )
                ->nullable()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                    $livewire->resetValidation('data.email');
                    $livewire->resetErrorBag('data.email');
                }),


            Hidden::make('postal_code')
                ->default('54000')
                ->dehydrateStateUsing(fn ($state) => $state ?: '54000'),
            Select::make('country_id')
                ->label('Country')
                ->relationship('country', 'name')
                ->options(fn (): array => GeoFormFields::countryOptions())
                ->default(fn (): ?string => GeoFormFields::defaultCountryId())
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (callable $set, $state, $old, $livewire) {
                    $set('city_id', null);
                    $livewire->resetValidation('data.country_id');
                    $livewire->resetErrorBag('data.country_id');
                }),

            Select::make('city_id')
                ->label('City')
                ->relationship(
                    'city',
                    'name',
                    fn ($query, callable $get) =>
                    $query->where('country_id', $get('country_id'))
                )
                ->createOptionForm([
                    Select::make('country_id')
                        ->label('Country')
                        ->options(fn (): array => GeoFormFields::countryOptions())
                        ->default(fn (callable $get) => $get('../../country_id') ?: GeoFormFields::defaultCountryId())
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('name')
                        ->label('City Name')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data): string => GeoFormFields::createCity($data))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                    $livewire->resetValidation('data.city_id');
                    $livewire->resetErrorBag('data.city_id');
                }),

            Hidden::make('merchant_id')
                ->default(fn () => match (true) {
                    Filament::auth()->user() instanceof Merchant
                    => Filament::auth()->user()->id,
                    Filament::auth()->user() instanceof User
                    => Filament::auth()->user()->merchant_id,
                    default => null,
                })
                ->required(),
            TextInput::make('occupation')
                ->label('Occupation')
                ->maxLength(255)
                ->nullable(),
            TextInput::make('reference')
                ->label('Reference Customer')
                ->maxLength(255)
                ->nullable(),

            TextInput::make('account_code')
                ->label('Account code')
                ->helperText('Legacy book/account reference used to find this customer in older registers.')
                ->maxLength(255)
                ->unique(
                    Customer::class,
                    'account_code',
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule): Unique {
                        $user = Filament::auth()->user();
                        $merchantId = match (true) {
                            $user instanceof Merchant => $user->id,
                            $user instanceof User => $user->merchant_id,
                            default => null,
                        };

                        return $rule->where('merchant_id', $merchantId)->withoutTrashed();
                    }
                )
                ->nullable(),

            Select::make('storage_billing_preference')
                ->label('Storage billing preference')
                ->helperText('Informational only — both billing actions stay available for every customer regardless of this setting. It just tells the clerk which one this customer normally expects.')
                ->placeholder('No preference set')
                ->options(Customer::storageBillingPreferenceOptions())
                ->native(false),

            Repeater::make('legacyReferences')
                ->label('Legacy register references')
                ->relationship()
                ->defaultItems(0)
                ->addActionLabel('Add legacy reference')
                ->helperText('One customer can have more than one book/page reference across seasons.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('season')->label('Season')->maxLength(255),
                    TextInput::make('book')->label('Book')->maxLength(255),
                    TextInput::make('page')->label('Page')->maxLength(255),
                    TextInput::make('note')->label('Note')->columnSpanFull(),
                ]),

            Select::make('branch_ids')
                ->label('Branches')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->options(fn () => CustomerResource::branchOptions(Filament::auth()->user()))
                ->helperText('Select the branches this customer belongs to. Businesses are derived automatically.')
                ->afterStateHydrated(function (Select $component, ?Customer $record, $state): void {
                    if (filled($state) || ! $record) {
                        return;
                    }

                    $component->state(
                        $record->branches()
                            ->pluck('branches.id')
                            ->all()
                    );
                })
                ->dehydrated(),
        ];
    }

    /**
     * ✅ Standard Filament schema entrypoint
     * Used by CustomerResource
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }
}
