<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Audit;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'audits';
    }

    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Reportings';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'event';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('audits')) {
            return false;
        }

        return $user->hasPermissionTo('audits.view', $guard);
    }

    public static function canView($record): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('audits.view', $guard);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $authUser = Filament::auth()->user();

        $merchantId = match (true) {
            $authUser instanceof Merchant => $authUser->id,
            $authUser instanceof User => $authUser->merchant_id,
            default => null,
        };

        if (! $merchantId) {
            return $query->whereRaw('1 = 0');
        }

        $staffIds = User::query()
            ->where('merchant_id', $merchantId)
            ->pluck('id');

        return $query->where(function (Builder $query) use ($merchantId, $staffIds) {
            $query
                ->where(function (Builder $query) use ($merchantId) {
                    $query
                        ->where('user_type', Merchant::class)
                        ->where('user_id', $merchantId);
                })
                ->orWhere(function (Builder $query) use ($staffIds) {
                    $query
                        ->where('user_type', User::class)
                        ->whereIn('user_id', $staffIds);
                });
        });
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
