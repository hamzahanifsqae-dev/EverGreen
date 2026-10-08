<?php

namespace App\Filament\Resources\ColdStorageReservations;

use App\Enums\ColdStorageCapacityUnit;
use App\Enums\ColdStorageWeightUnit;
use App\Filament\Concerns\ColdStorageSubmoduleNavigation;
use App\Filament\Resources\ColdStorage\ColdStorageResource;
use App\Filament\Resources\ColdStorageReservations\Pages\CreateColdStorageReservation;
use App\Filament\Resources\ColdStorageReservations\Pages\EditColdStorageReservation;
use App\Filament\Resources\ColdStorageReservations\Pages\ListColdStorageReservations;
use App\Filament\Resources\ColdStorageReservations\Pages\ViewColdStorageReservation;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageReservation;
use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColdStorageReservationResource extends ColdStorageResource
{
    use ColdStorageSubmoduleNavigation;

    protected static function coldStorageUiModuleKey(): ?string
    {
        return 'cold_storage_reservations';
    }

    protected static ?string $model = ColdStorageReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static ?string $navigationLabel = 'Reservations';

    protected static ?string $modelLabel = 'Reservation';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'reservation_no';

    public static function shouldRegisterNavigation(): bool
    {
        if (! UiModules::enabled('cold_storage_reservations')) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('reservation_no')
                        ->label('Reservation number')
                        ->default(fn (): string => 'RS-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->dehydrated(),
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
                    Select::make('chamber_id')
                        ->label('Chamber')
                        ->options(fn (callable $get): array => ColdStorageAccess::scope(
                            ColdStorageChamber::query()->when(filled($get('branch_id')), fn ($q) => $q->where('branch_id', $get('branch_id')))
                        )->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    DatePicker::make('reserved_from')
                        ->label('Reserved from')
                        ->required()
                        ->default(now())
                        ->displayFormat('d/m/Y')
                        ->native(false),
                    DatePicker::make('reserved_until')
                        ->label('Reserved until')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->minDate(fn (callable $get) => $get('reserved_from')),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Expected quantity')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('expected_packages')
                        ->numeric()
                        ->label('Expected packages'),
                    TextInput::make('expected_weight')
                        ->numeric()
                        ->label('Expected weight'),
                    Select::make('weight_unit')
                        ->label('Weight unit')
                        ->options(ColdStorageWeightUnit::options())
                        ->preload(),
                    TextInput::make('expected_capacity')
                        ->numeric()
                        ->label('Expected capacity'),
                    Select::make('capacity_unit')
                        ->label('Capacity unit')
                        ->options(ColdStorageCapacityUnit::options())
                        ->preload(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('reservation_no')->label('Reservation number'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('chamber.name')->label('Chamber'),
                    TextEntry::make('reserved_from')->date('d/m/Y'),
                    TextEntry::make('reserved_until')->date('d/m/Y'),
                    TextEntry::make('receipt.receipt_no')->label('Linked receipt'),
                    TextEntry::make('notes')->columnSpanFull(),
                ]),
            Section::make('Expected quantity')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('expected_packages'),
                    TextEntry::make('expected_weight'),
                    TextEntry::make('weight_unit'),
                    TextEntry::make('expected_capacity'),
                    TextEntry::make('capacity_unit'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reservation_no')->label('Booking')->searchable()->sortable(),
                TextColumn::make('branch.name')->label('Branch')->toggleable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('chamber.name')->label('Chamber')->toggleable(),
                TextColumn::make('reserved_from')->date('d/m/Y')->sortable(),
                TextColumn::make('reserved_until')->date('d/m/Y')->toggleable(),
                TextColumn::make('expected_packages')->label('Packages')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (ColdStorageReservation $record): bool => static::canEdit($record)),
            ])
            ->defaultSort('reserved_from', 'desc')
            ->emptyStateHeading('No reservations yet')
            ->emptyStateDescription('Create a booking when a customer reserves chamber space in advance.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColdStorageReservations::route('/'),
            'create' => CreateColdStorageReservation::route('/create'),
            'view' => ViewColdStorageReservation::route('/{record}'),
            'edit' => EditColdStorageReservation::route('/{record}/edit'),
        ];
    }
}
