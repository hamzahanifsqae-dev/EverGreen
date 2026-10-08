<?php

namespace App\Filament\Resources\ColdStorageTemperatureReadings;

use App\Enums\ColdStorageTemperatureSource;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageTemperatureReadings\Pages\CreateColdStorageTemperatureReading;
use App\Filament\Resources\ColdStorageTemperatureReadings\Pages\ListColdStorageTemperatureReadings;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageTemperatureReading;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColdStorageTemperatureReadingResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageTemperatureReading::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Sun;

    protected static ?string $navigationLabel = 'Temperature';

    protected static ?string $modelLabel = 'Temperature reading';

    protected static ?int $navigationSort = 8;

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reading')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('chamber_id')
                        ->label('Chamber')
                        ->options(function (): array {
                            return ColdStorageAccess::scope(ColdStorageChamber::query())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    DateTimePicker::make('recorded_at')
                        ->label('Recorded at')
                        ->default(now())
                        ->required()
                        ->native(false),
                    TextInput::make('temperature')
                        ->numeric()
                        ->required()
                        ->suffix(config('cold-storage.defaults.temperature_unit')),
                    Select::make('source')
                        ->options([
                            ColdStorageTemperatureSource::Manual->value => ColdStorageTemperatureSource::Manual->label(),
                            ColdStorageTemperatureSource::Sensor->value => ColdStorageTemperatureSource::Sensor->label(),
                        ])
                        ->default('manual')
                        ->required()
                        ->live(),
                    TextInput::make('sensor_reference')
                        ->label('Sensor reference')
                        ->visible(fn (callable $get): bool => $get('source') === ColdStorageTemperatureSource::Sensor->value)
                        ->helperText('Reserved for a future sensor feed.'),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('chamber.name')->label('Chamber')->searchable(),
            TextColumn::make('recorded_at')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('temperature'),
            TextColumn::make('temperature_unit')->label('Unit'),
            TextColumn::make('is_out_of_range')
                ->label('Exception')
                ->badge()
                ->formatStateUsing(fn (?bool $state): string => $state ? 'Out of range' : 'Within range')
                ->color(fn (?bool $state): string => $state ? 'danger' : 'success'),
            TextColumn::make('source')->badge(),
            TextColumn::make('recordedBy.name')->label('Recorded by')->toggleable(),
        ])->defaultSort('recorded_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageTemperatureReadings::route('/'),
            'create' => CreateColdStorageTemperatureReading::route('/create'),
        ];
    }
}
