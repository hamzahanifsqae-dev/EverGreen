<?php

namespace App\Filament\Resources\ColdStorageRateCards;

use App\Enums\ColdStorageChargeBasis;
use App\Enums\ColdStorageChargePeriod;
use App\Enums\ColdStorageRoundingMode;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageRateCards\Pages\CreateColdStorageRateCard;
use App\Filament\Resources\ColdStorageRateCards\Pages\EditColdStorageRateCard;
use App\Filament\Resources\ColdStorageRateCards\Pages\ListColdStorageRateCards;
use App\Models\ColdStorageRateCard;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColdStorageRateCardResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageRateCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $navigationLabel = 'Storage rates';

    protected static ?string $modelLabel = 'Storage rate';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Parties')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn (): array => ColdStorageAccess::customerOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => ColdStorageAccess::branchOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('Leave empty to apply this rate at every branch.'),
                ]),
            Section::make('Pricing')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
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
                    TextInput::make('rate')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix(config('cold-storage.currency')),
                    TextInput::make('minimum_charge')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->prefix(config('cold-storage.currency')),
                    DatePicker::make('effective_from')
                        ->label('Effective from')
                        ->required()
                        ->default(now())
                        ->displayFormat('d/m/Y')
                        ->native(false),
                    DatePicker::make('effective_to')
                        ->label('Effective to')
                        ->displayFormat('d/m/Y')
                        ->native(false),
                ]),
            Section::make('Billing rules')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('rounding_mode')
                        ->label('Rounding')
                        ->options(ColdStorageRoundingMode::options())
                        ->default(config('cold-storage.defaults.rounding_mode'))
                        ->required(),
                    Toggle::make('bill_arrival_day')
                        ->label('Bill the arrival day')
                        ->default((bool) config('cold-storage.defaults.bill_arrival_day')),
                    Toggle::make('bill_departure_day')
                        ->label('Bill the departure day')
                        ->default((bool) config('cold-storage.defaults.bill_departure_day')),
                    TextInput::make('season_length_days')
                        ->label('Season length (days)')
                        ->numeric()
                        ->default(config('cold-storage.defaults.season_length_days'))
                        ->minValue(1)
                        ->required(),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('branch.name')->label('Branch')->placeholder('All branches'),
            TextColumn::make('charge_basis')->badge(),
            TextColumn::make('charge_period')->badge(),
            TextColumn::make('rate')->money(config('cold-storage.currency')),
            TextColumn::make('minimum_charge')->money(config('cold-storage.currency'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('effective_from')->date('d/m/Y'),
            TextColumn::make('effective_to')->date('d/m/Y')->placeholder('Open'),
        ])->recordActions([
            EditAction::make(),
        ])->defaultSort('effective_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageRateCards::route('/'),
            'create' => CreateColdStorageRateCard::route('/create'),
            'edit' => EditColdStorageRateCard::route('/{record}/edit'),
        ];
    }
}
