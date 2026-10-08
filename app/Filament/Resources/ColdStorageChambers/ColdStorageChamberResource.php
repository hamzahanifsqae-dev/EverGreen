<?php

namespace App\Filament\Resources\ColdStorageChambers;

use App\Enums\ColdStorageCapacityUnit;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageChambers\Pages\CreateColdStorageChamber;
use App\Filament\Resources\ColdStorageChambers\Pages\EditColdStorageChamber;
use App\Filament\Resources\ColdStorageChambers\Pages\ListColdStorageChambers;
use App\Models\ColdStorageChamber;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColdStorageChamberResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageChamber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    protected static ?string $navigationLabel = 'Chambers';

    protected static ?string $modelLabel = 'Chamber';

    protected static ?int $navigationSort = 1;

    public static function canDelete($record): bool
    {
        return parent::canDelete($record)
            && $record instanceof ColdStorageChamber
            && ! $record->movements()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Chamber')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->maxLength(50),
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => ColdStorageAccess::branchOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    TextInput::make('capacity_quantity')
                        ->label('Capacity')
                        ->numeric()
                        ->required()
                        ->minValue(0.001),
                    Select::make('capacity_unit')
                        ->label('Unit')
                        ->options(ColdStorageCapacityUnit::options())
                        ->required()
                        ->default(ColdStorageCapacityUnit::Bag->value),
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('min_temperature')
                        ->label('Minimum temperature')
                        ->numeric(),
                    TextInput::make('max_temperature')
                        ->label('Maximum temperature')
                        ->numeric(),
                    TextInput::make('temperature_unit')
                        ->label('Temperature unit')
                        ->default(config('cold-storage.defaults.temperature_unit'))
                        ->maxLength(8),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Racks and bins')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('locations')
                        ->relationship()
                        ->defaultItems(0)
                        ->addActionLabel('Add rack / bin')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('code'),
                            TextInput::make('capacity_quantity')
                                ->label('Capacity')
                                ->numeric()
                                ->minValue(0),
                            Toggle::make('is_active')->label('Active')->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('code')->toggleable(),
            TextColumn::make('business.name')->label('Business'),
            TextColumn::make('branch.name')->label('Branch'),
            TextColumn::make('capacity_quantity')
                ->label('Capacity')
                ->formatStateUsing(fn ($state, ColdStorageChamber $record): string => trim((string) $state.' '.$record->capacity_unit)),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageChambers::route('/'),
            'create' => CreateColdStorageChamber::route('/create'),
            'edit' => EditColdStorageChamber::route('/{record}/edit'),
        ];
    }
}
