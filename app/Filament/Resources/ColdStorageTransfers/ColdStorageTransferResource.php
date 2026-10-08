<?php

namespace App\Filament\Resources\ColdStorageTransfers;

use App\Filament\ColdStorage\LotOptions;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageTransfers\Pages\CreateColdStorageTransfer;
use App\Filament\Resources\ColdStorageTransfers\Pages\EditColdStorageTransfer;
use App\Filament\Resources\ColdStorageTransfers\Pages\ListColdStorageTransfers;
use App\Filament\Resources\ColdStorageTransfers\Pages\ViewColdStorageTransfer;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageTransfer;
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
use Filament\Tables\Table;

class ColdStorageTransferResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowPath;

    protected static ?string $navigationLabel = 'Transfers';

    protected static ?string $modelLabel = 'Transfer';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'transfer_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transfer')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('transfer_no')
                        ->label('Transfer number')
                        ->default(fn (): string => 'GT-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
                    DatePicker::make('transferred_on')
                        ->label('Transfer date')
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
                        ->live()
                        ->columnSpan(2),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Lines')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->defaultItems(1)
                        ->addActionLabel('Add transfer line')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            Select::make('receipt_item_id')
                                ->label('Lot')
                                ->options(fn (callable $get): array => LotOptions::forDocument($get))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),
                            Select::make('from_chamber_id')
                                ->label('From chamber')
                                ->options(fn (callable $get): array => self::chambers($get))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('from_location_id')
                                ->label('From location')
                                ->options(fn (callable $get): array => self::locations($get('from_chamber_id')))
                                ->searchable()
                                ->preload(),
                            Select::make('to_chamber_id')
                                ->label('To chamber')
                                ->options(fn (callable $get): array => self::chambers($get))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('to_location_id')
                                ->label('To location')
                                ->options(fn (callable $get): array => self::locations($get('to_chamber_id')))
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
            Section::make('Transfer')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('transfer_no')->label('Transfer number'),
                    TextEntry::make('transferred_on')->date('d/m/Y'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('notes')->columnSpanFull(),
                ]),
            Section::make('Lines')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('receiptItem.lot_number')->label('Lot'),
                            TextEntry::make('fromChamber.name')->label('From chamber'),
                            TextEntry::make('toChamber.name')->label('To chamber'),
                            TextEntry::make('package_count')->label('Packages'),
                            TextEntry::make('fromLocation.name')->label('From location')->placeholder('—'),
                            TextEntry::make('toLocation.name')->label('To location')->placeholder('—'),
                            TextEntry::make('net_weight')->label('Weight'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('transfer_no')->label('Transfer')->searchable()->sortable(),
            TextColumn::make('transferred_on')->date('d/m/Y')->sortable(),
            TextColumn::make('branch.name')->label('Branch')->toggleable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('status')->badge(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
        ])->defaultSort('transferred_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageTransfers::route('/'),
            'create' => CreateColdStorageTransfer::route('/create'),
            'view' => ViewColdStorageTransfer::route('/{record}'),
            'edit' => EditColdStorageTransfer::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function chambers(callable $get): array
    {
        $branchId = $get('../branch_id');

        return ColdStorageChamber::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function locations(?string $chamberId): array
    {
        if (! $chamberId) {
            return [];
        }

        return ColdStorageLocation::query()
            ->where('chamber_id', $chamberId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
