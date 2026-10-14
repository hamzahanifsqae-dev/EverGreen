<?php

namespace App\Support;

class UiModules
{
    public static function enabled(?string $module): bool
    {
        if ($module === null || $module === '') {
            return true;
        }

        if ((bool) config("ui-modules.enabled.{$module}", true)) {
            return true;
        }

        // Only the modules listed in peekableModules() can be revealed again
        // by the "show hidden modules" checkbox in the sidebar, for the
        // current browser session only. Everything else stays out of the UI
        // regardless of the checkbox.
        if (! in_array($module, self::peekableModules(), true)) {
            return false;
        }

        return self::hiddenModulesVisible();
    }

    public static function hiddenModulesVisible(): bool
    {
        return (bool) session('show_hidden_modules', false);
    }

    /**
     * @return list<string>
     */
    public static function peekableModules(): array
    {
        return [
            'cold_storage_adjustments',
        ];
    }

    /**
     * Map dashboard overview cards to ui-module keys.
     *
     * @return array<string, list<string>>
     */
    public static function dashboardOverviewModuleMap(): array
    {
        return [
            'sales' => ['sales'],
            'purchases' => ['purchases'],
            'profit_loss' => ['sales', 'purchases'],
            'stock' => ['stock_report'],
            'inventory_movement' => ['inventory_movement_report'],
            'expenses' => ['expenses'],
            'funds' => ['cash_flows', 'sales'],
            'cash_flow' => ['cash_flows'],
        ];
    }

    public static function dashboardOverviewEnabled(string $overviewKey): bool
    {
        $required = self::dashboardOverviewModuleMap()[$overviewKey] ?? [];

        if ($required === []) {
            return true;
        }

        foreach ($required as $module) {
            if (self::enabled($module)) {
                return true;
            }
        }

        return false;
    }

    public static function anyDashboardOverviewEnabled(): bool
    {
        foreach (array_keys(self::dashboardOverviewModuleMap()) as $key) {
            if (self::dashboardOverviewEnabled($key)) {
                return true;
            }
        }

        return false;
    }
}
