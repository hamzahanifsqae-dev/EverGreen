<?php

namespace App\Filament\Resources\ColdStorageDispatches;

use App\Filament\ColdStorage\LotOptions;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageDispatches\Pages\CreateColdStorageDispatch;
use App\Filament\Resources\ColdStorageDispatches\Pages\EditColdStorageDispatch;
use App\Filament\Resources\ColdStorageDispatches\Pages\ListColdStorageDispatches;
use App\Filament\Resources\ColdStorageDispatches\Pages\ViewColdStorageDispatch;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageLocation;
use App\Support\ColdStorageAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ColdStorageDispatchResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageDispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Truck;

    protected static ?string $navigationLabel = 'Return';

    protected static ?string $modelLabel = 'Return';

    protected static ?string $pluralModelLabel = 'Return';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'dispatch_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Return')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('dispatch_no')
                        ->label('Return number')
                        ->default(fn (): string => 'GD-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
                    DatePicker::make('dispatched_on')
                        ->label('Return date')
                        ->default(now())
                        ->required()
                        ->displayFormat('d/m/Y')
                        ->native(false),
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => ColdStorageAccess::branchOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn (): array => ColdStorageAccess::customerOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    TextInput::make('recipient_name')
                        ->label('Recipient')
                        ->required(),
                    TextInput::make('vehicle_number')
                        ->label('Vehicle number'),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Withdrawal')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->defaultItems(1)
                        ->addActionLabel('Add withdrawal line')
                        ->columnSpanFull()
                        ->columns(3)
                        ->schema([
                            Select::make('receipt_item_id')
                                ->label('Lot')
                                ->options(fn (callable $get): array => LotOptions::forDocument($get))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),
                            Select::make('chamber_id')
                                ->label('Chamber')
                                ->options(function (callable $get): array {
                                    $branchId = $get('../branch_id');

                                    return ColdStorageChamber::query()
                                        ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('location_id')
                                ->label('Location')
                                ->options(fn (callable $get): array => $get('chamber_id')
                                    ? ColdStorageLocation::query()->where('chamber_id', $get('chamber_id'))->orderBy('name')->pluck('name', 'id')->all()
                                    : [])
                                ->searchable()
                                ->preload(),
                            TextInput::make('package_count')
                                ->label('Packages')
                                ->numeric()
                                ->required()
                                ->minValue(0.001),
                            TextInput::make('net_weight')
                                ->label('Weight')
                                ->numeric()
                                ->required()
                                ->minValue(0.001),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Return')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('dispatch_no')->label('Return number'),
                    TextEntry::make('dispatched_on')->label('Return date')->date('d/m/Y'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('recipient_name')->label('Recipient'),
                    TextEntry::make('vehicle_number')->label('Vehicle'),
                    TextEntry::make('notes')->columnSpanFull(),
                ]),
            Section::make('Withdrawal')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->columnSpanFull()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('receiptItem.lot_number')->label('Lot'),
                            TextEntry::make('chamber.name')->label('Chamber'),
                            TextEntry::make('location.name')->label('Location')->placeholder('—'),
                            TextEntry::make('package_count')->label('Packages'),
                            TextEntry::make('net_weight')->label('Weight'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('dispatch_no')->label('Return')->searchable()->sortable(),
            TextColumn::make('dispatched_on')->label('Return date')->date('d/m/Y')->sortable(),
            TextColumn::make('branch.name')->label('Branch')->toggleable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('recipient_name')->label('Recipient'),
            TextColumn::make('vehicle_number')->label('Vehicle')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge(),
        ])->filters([
            SelectFilter::make('customer_id')
                ->label('Customer')
                ->options(fn (): array => ColdStorageAccess::customerOptions())
                ->searchable()
                ->preload(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make()
                ->visible(fn (ColdStorageDispatch $record): bool => static::canEdit($record)),
        ])->defaultSort('dispatched_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageDispatches::route('/'),
            'create' => CreateColdStorageDispatch::route('/create'),
            'view' => ViewColdStorageDispatch::route('/{record}'),
            'edit' => EditColdStorageDispatch::route('/{record}/edit'),
        ];
    }
}
