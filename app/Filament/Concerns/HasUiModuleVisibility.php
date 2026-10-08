<?php

namespace App\Filament\Concerns;

use App\Support\UiModules;

trait HasUiModuleVisibility
{
    public static function shouldRegisterNavigation(): bool
    {
        if (! UiModules::enabled(static::uiModuleKey())) {
            return false;
        }

        return static::$shouldRegisterNavigation;
    }

    /**
     * Key from config/ui-modules.php. Override in each resource/page.
     */
    protected static function uiModuleKey(): ?string
    {
        return null;
    }
}
