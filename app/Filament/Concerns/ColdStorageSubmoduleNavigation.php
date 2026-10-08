<?php

namespace App\Filament\Concerns;

use App\Support\UiModules;

trait ColdStorageSubmoduleNavigation
{
    protected static function coldStorageUiModuleKey(): ?string
    {
        return null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! UiModules::enabled('cold_storage')) {
            return false;
        }

        $moduleKey = static::coldStorageUiModuleKey();

        if ($moduleKey !== null && ! UiModules::enabled($moduleKey)) {
            return false;
        }

        return static::$shouldRegisterNavigation;
    }
}
