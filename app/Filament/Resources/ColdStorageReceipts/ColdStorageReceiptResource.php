<?php

namespace App\Filament\Resources\ColdStorageReceipts;

use App\Enums\ColdStorageWeightUnit;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageReceipts\Pages\CreateColdStorageReceipt;
use App\Filament\Resources\ColdStorageReceipts\Pages\EditColdStorageReceipt;
use App\Filament\Resources\ColdStorageReceipts\Pages\ListColdStorageReceipts;
use App\Filament\Resources\ColdStorageReceipts\Pages\ViewColdStorageReceipt;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
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

class ColdStorageReceiptResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Inbox;

    protected static ?string $navigationLabel = 'Goods receipts';

    protected static ?string $modelLabel = 'Goods receipt';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'receipt_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receipt')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('receipt_no')
                        ->label('Receipt number')
                        ->default(fn (): string => 'GR-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
                    DatePicker::make('received_on')
                        ->label('Receiving date')
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
                        ->required(),
                    TextInput::make('vehicle_number')
                        ->label('Vehicle number'),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Goods')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->defaultItems(1)
                        ->addActionLabel('Add goods line')
                        ->columnSpanFull()
                        ->columns(3)
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(function (): array {
                                    $merchantId = ColdStorageAccess::merchantId();

                                    return Product::query()
                                        ->when($merchantId, fn ($query) => $query->where('merchant_id', $merchantId))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('product_variant_id')
                                ->label('Variant')
                                ->options(function (callable $get): array {
                                    if (! $get('product_id')) {
                                        return [];
                                    }

                                    return ProductVariant::query()
                                        ->where('product_id', $get('product_id'))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload(),
                            TextInput::make('lot_number')
                                ->label('Lot number')
                                ->required(),
                            TextInput::make('package_count')
                                ->label('Bags / packages')
                                ->numeric()
                                ->required()
                                ->minValue(0.001),
                            TextInput::make('net_weight')
                                ->label('Net weight')
                                ->numeric()
                                ->required()
                                ->minValue(0.001),
                            Select::make('weight_unit')
                                ->label('Weight unit')
                                ->options(ColdStorageWeightUnit::options())
                                ->required()
                                ->default('kilogram'),
                            Textarea::make('notes')
                                ->rows(2)
                                ->columnSpanFull(),
                            Repeater::make('allocations')
                                ->relationship()
                                ->defaultItems(1)
                                ->addActionLabel('Add allocation')
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('chamber_id')
                                        ->label('Chamber')
                                        ->options(function (callable $get): array {
                                            $branchId = $get('../../branch_id');

                                            return ColdStorageChamber::query()
                                                ->where('is_active', true)
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
                                        ->label('Rack / bin')
                                        ->options(function (callable $get): array {
                                            if (! $get('chamber_id')) {
                                                return [];
                                            }

                                            return ColdStorageLocation::query()
                                                ->where('chamber_id', $get('chamber_id'))
                                                ->where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload(),
                                    TextInput::make('package_count')
                                        ->label('Packages')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0),
                                    TextInput::make('net_weight')
                                        ->label('Weight')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receipt')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('receipt_no')->label('Receipt number'),
                    TextEntry::make('received_on')->date('d/m/Y'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('vehicle_number')->label('Vehicle'),
                    TextEntry::make('notes')->columnSpanFull(),
                ]),
            Section::make('Goods')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->columnSpanFull()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('product.name')->label('Product'),
                            TextEntry::make('lot_number')->label('Lot'),
                            TextEntry::make('package_count')->label('Packages'),
                            TextEntry::make('net_weight')->label('Net weight'),
                            TextEntry::make('weight_unit')->label('Unit'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('receipt_no')->label('Receipt')->searchable()->sortable(),
            TextColumn::make('received_on')->date('d/m/Y')->sortable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('branch.name')->label('Branch'),
            TextColumn::make('vehicle_number')->label('Vehicle')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
        ])->defaultSort('received_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageReceipts::route('/'),
            'create' => CreateColdStorageReceipt::route('/create'),
            'view' => ViewColdStorageReceipt::route('/{record}'),
            'edit' => EditColdStorageReceipt::route('/{record}/edit'),
        ];
    }
}
