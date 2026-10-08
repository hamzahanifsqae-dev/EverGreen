<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Models\User;
use App\Support\GeoFormFields;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {

        $user = Filament::auth()->user();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Business Name')
                    ->maxLength(250)
                    ->required()
                    ->live()
                    ->rules([
                        fn ($get) => Rule::unique('businesses', 'name')
                            ->where(fn ($query) => $query->where('merchant_id', $get('merchant_id'))
                                ->whereNull('deleted_at')
                            )
                            ->ignore($get('id')),
                    ])
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $livewire->resetValidation('data.name');
                        $livewire->resetErrorBag('data.name');
                    }),
                FileUpload::make('business_logo')
                    ->label('Business Logo')
                    ->image()
                    ->disk('public')
                    ->directory('business/logos')
                    ->visibility('public')
                    ->imagePreviewHeight(120)
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->dehydrated(false),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->maxLength(400),
                Select::make('countries')
                    ->label('Countries')
                    ->relationship('countries', 'name')
                    ->multiple()
                    ->options(fn (): array => GeoFormFields::countryOptions())
                    ->default(fn (): array => GeoFormFields::defaultCountryIds())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (callable $set, $state, $old, $livewire) {

                        // Reset cities when countries change
                        $set('cities', []);

                        // Reset validation properly
                        $livewire->resetValidation('data.countries');
                        $livewire->resetErrorBag('data.countries');
                        $livewire->resetValidation('data.cities');
                        $livewire->resetErrorBag('data.cities');
                    }),

                Select::make('cities')
                    ->label('Cities')
                    ->relationship(
                        'cities',
                        'name',
                        fn ($query, callable $get) => $query->whereIn('country_id', $get('countries') ?? [])
                    )
                    ->multiple()
                    ->createOptionForm([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(fn (): array => GeoFormFields::countryOptions())
                            ->default(fn (callable $get) => ($get('../../countries') ?? [])[0] ?? GeoFormFields::defaultCountryId())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('City Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(fn (array $data): string => GeoFormFields::createCity($data))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                        $livewire->resetValidation('data.cities');
                        $livewire->resetErrorBag('data.cities');
                    }),

                Grid::make(2)
                    ->schema([

                        TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->placeholder('e.g. 54000')
                            ->regex('/^\d{1,12}$/')
                            ->minLength(5)
                            ->maxLength(12)
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                                $livewire->resetValidation('data.postal_code');
                                $livewire->resetErrorBag('data.postal_code');
                            }),

                    ])
                    ->columnSpanFull()
                    ->columns(2),

                Toggle::make('status')
                    ->label('Active')
                    ->default(true)
                    ->required(),
                Hidden::make('merchant_id')
                    ->default(fn () => $user instanceof User
                        ? $user->merchant_id
                        : $user?->id
                    ),
            ]);

    }
}
