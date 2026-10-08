<?php

namespace App\Filament\Resources\ColdStorageAdjustments;

use App\Enums\ColdStorageAdjustmentKind;
use App\Filament\ColdStorage\LotOptions;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageAdjustments\Pages\CreateColdStorageAdjustment;
use App\Filament\Resources\ColdStorageAdjustments\Pages\EditColdStorageAdjustment;
use App\Filament\Resources\ColdStorageAdjustments\Pages\ListColdStorageAdjustments;
use App\Filament\Resources\ColdStorageAdjustments\Pages\ViewColdStorageAdjustment;
use App\Models\ColdStorageAdjustment;
use App\Models\ColdStorageChamber;
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
use Filament\Tables\Table;

class ColdStorageAdjustmentResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    protected static ?string $navigationLabel = 'Damage and adjustments';

    protected static ?string $modelLabel = 'Adjustment';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'adjustment_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Adjustment')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('adjustment_no')
                        ->label('Adjustment number')
                        ->default(fn (): string => 'GA-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
                    Select::make('kind')
                        ->label('Kind')
                        ->options(ColdStorageAdjustmentKind::options())
                        ->required()
                        ->default('damage'),
                    DatePicker::make('adjusted_on')
                        ->label('Adjusted on')
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
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Lines')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->defaultItems(1)
                        ->addActionLabel('Add line')
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
                            TextInput::make('package_delta')
                                ->label('Package change')
                                ->numeric()
                                ->required()
                                ->helperText('Use a negative number to remove stock.'),
                            TextInput::make('weight_delta')
                                ->label('Weight change')
                                ->numeric()
                                ->required(),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Adjustment')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('adjustment_no')->label('Adjustment number'),
                    TextEntry::make('kind')->badge(),
                    TextEntry::make('adjusted_on')->date('d/m/Y'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('reason')->columnSpanFull(),
                ]),
            Section::make('Lines')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->columnSpanFull()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('receiptItem.lot_number')->label('Lot'),
                            TextEntry::make('chamber.name')->label('Chamber'),
                            TextEntry::make('location.name')->label('Location')->placeholder('—'),
                            TextEntry::make('package_delta')->label('Package change'),
                            TextEntry::make('weight_delta')->label('Weight change'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('adjustment_no')->label('Adjustment')->searchable()->sortable(),
            TextColumn::make('adjusted_on')->date('d/m/Y')->sortable(),
            TextColumn::make('branch.name')->label('Branch')->toggleable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('kind')->badge(),
            TextColumn::make('reason')->limit(40)->toggleable(),
            TextColumn::make('status')->badge(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
        ])->defaultSort('adjusted_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageAdjustments::route('/'),
            'create' => CreateColdStorageAdjustment::route('/create'),
            'view' => ViewColdStorageAdjustment::route('/{record}'),
            'edit' => EditColdStorageAdjustment::route('/{record}/edit'),
        ];
    }
}
