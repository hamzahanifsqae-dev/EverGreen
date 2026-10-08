<?php

namespace App\Filament\Resources\ColdStorage;

use App\Support\ColdStorageAccess;
use App\Support\UiModules;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

abstract class ColdStorageResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Cold Storage';

    public static function shouldRegisterNavigation(): bool
    {
        return UiModules::enabled('cold_storage');
    }

    public static function canViewAny(): bool
    {
        return ColdStorageAccess::can('view');
    }

    public static function canCreate(): bool
    {
        return ColdStorageAccess::can('create');
    }

    public static function canEdit($record): bool
    {
        if (! ColdStorageAccess::can('update')) {
            return false;
        }

        return static::recordAllowsEditing($record);
    }

    public static function canDelete($record): bool
    {
        if (! ColdStorageAccess::can('delete')) {
            return false;
        }

        return static::recordAllowsEditing($record);
    }

    public static function recordAllowsEditing($record): bool
    {
        if (isset($record->status)) {
            return $record->status === 'draft';
        }

        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return ColdStorageAccess::scope(parent::getEloquentQuery());
    }
}
