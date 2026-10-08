<?php

namespace App\Filament\Resources\ColdStorageBills;

use App\Enums\ColdStorageChargeBasis;
use App\Enums\ColdStorageChargePeriod;
use App\Enums\ColdStorageServiceBasis;
use App\Filament\ColdStorage\ColdStorageActions;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageBills\Pages\CreateColdStorageBill;
use App\Filament\Resources\ColdStorageBills\Pages\EditColdStorageBill;
use App\Filament\Resources\ColdStorageBills\Pages\ListColdStorageBills;
use App\Filament\Resources\ColdStorageBills\Pages\ViewColdStorageBill;
use App\Models\ColdStorageBill;
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
use Illuminate\Database\Eloquent\Builder;

class ColdStorageBillResource extends ColdStorageResource
{
    protected static ?string $model = ColdStorageBill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $navigationLabel = 'Storage bills';

    protected static ?string $modelLabel = 'Storage bill';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'bill_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bill')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('bill_no')
                        ->label('Bill number')
                        ->default(fn (): string => 'SB-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => ColdStorageAccess::branchOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn (): array => ColdStorageAccess::customerOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    DatePicker::make('period_start')
                        ->label('Period start')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->displayFormat('d/m/Y')
                        ->native(false),
                    DatePicker::make('period_end')
                        ->label('Period end')
                        ->required()
                        ->default(now())
                        ->displayFormat('d/m/Y')
                        ->native(false),
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
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Loading, unloading, and other services')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('services')
                        ->relationship()
                        ->defaultItems(0)
                        ->addActionLabel('Add service')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            TextInput::make('name')->required(),
                            Select::make('basis')
                                ->options(ColdStorageServiceBasis::options())
                                ->required()
                                ->default('flat'),
                            TextInput::make('quantity')->numeric()->default(1)->required(),
                            TextInput::make('rate')
                                ->numeric()
                                ->required()
                                ->prefix(config('cold-storage.currency')),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bill')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('bill_no')->label('Bill number'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('period_start')->date('d/m/Y'),
                    TextEntry::make('period_end')->date('d/m/Y'),
                    TextEntry::make('charge_basis'),
                    TextEntry::make('charge_period'),
                    TextEntry::make('storage_total')->money(config('cold-storage.currency')),
                    TextEntry::make('service_total')->money(config('cold-storage.currency')),
                    TextEntry::make('total_amount')->money(config('cold-storage.currency')),
                    TextEntry::make('paid_amount')->money(config('cold-storage.currency')),
                    TextEntry::make('due_amount')->money(config('cold-storage.currency')),
                    TextEntry::make('notes')->columnSpanFull(),
                ]),
            Section::make('Storage lines')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->columnSpanFull()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('lot_number')->label('Lot'),
                            TextEntry::make('period_start')->date('d/m/Y'),
                            TextEntry::make('period_end')->date('d/m/Y'),
                            TextEntry::make('quantity_days')->label('Quantity-days'),
                            TextEntry::make('rate'),
                            TextEntry::make('line_total')->money(config('cold-storage.currency')),
                            TextEntry::make('calculation_note')->columnSpanFull(),
                        ]),
                ]),
            Section::make('Services')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('services')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('basis'),
                            TextEntry::make('quantity'),
                            TextEntry::make('rate')->money(config('cold-storage.currency')),
                        ]),
                ]),
            Section::make('Payments received')
                ->columnSpanFull()
                ->visible(fn ($record): bool => $record->payments->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('payments')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('payment_date')->date('d/m/Y')->label('Date'),
                            TextEntry::make('amount')->money(config('cold-storage.currency')),
                            TextEntry::make('method')->badge(),
                            TextEntry::make('reference_no')->label('Reference'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('bill_no')->label('Bill')->searchable()->sortable(),
            TextColumn::make('branch.name')->label('Branch')->toggleable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('period_start')->date('d/m/Y')->sortable(),
            TextColumn::make('period_end')->date('d/m/Y'),
            TextColumn::make('total_amount')->money(config('cold-storage.currency'))->sortable(),
            TextColumn::make('paid_amount')->money(config('cold-storage.currency'))->sortable()->toggleable(),
            TextColumn::make('due_amount')->money(config('cold-storage.currency'))->sortable(),
            TextColumn::make('status')->badge(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make()
                ->visible(fn (ColdStorageBill $record): bool => static::canEdit($record)),
            ColdStorageActions::invoice(),
        ])->defaultSort('period_start', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['payments']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageBills::route('/'),
            'create' => CreateColdStorageBill::route('/create'),
            'view' => ViewColdStorageBill::route('/{record}'),
            'edit' => EditColdStorageBill::route('/{record}/edit'),
        ];
    }
}
