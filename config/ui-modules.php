<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UI module visibility
    |--------------------------------------------------------------------------
    |
    | Flip a flag to false to hide that module from the Filament sidebar.
    | The code, routes, and permissions stay in place — set it back to true
    | whenever you want the menu item again.
    |
    | This only controls navigation. Direct URLs may still work if the user
    | already has permission.
    |
    */

    'enabled' => [

        // Cold storage workflow
        'cold_storage' => true,
        'cold_storage_action_alerts' => true,
        'cold_storage_alert_emails' => false,
        'cold_storage_lot_ageing' => true,
        'cold_storage_quick_bill' => true,
        'cold_storage_capacity_heatmap' => true,
        'cold_storage_reservations' => true,
        'customers' => true,
        'products' => true,
        'categories' => true,

        // Setup / admin
        'dashboard' => true,
        'businesses' => true,
        'branches' => true,
        'staff' => true,
        'merchants' => true,
        'roles' => true,

        // Procurement / sales (hidden for cold-storage focus)
        'sales' => false,
        'sale_returns' => false,
        'purchases' => false,
        'purchase_returns' => false,
        'vendors' => false,
        'expenses' => false,
        'cash_flows' => false,

        // Extra inventory
        'brands' => false,
        'brand_models' => false,
        'product_variants' => false,

        // Assets / HR
        'assets' => false,
        'asset_types' => false,
        'payrolls' => false,

        // Classic CRM reports
        'stock_report' => false,
        'inventory_movement_report' => false,
        'purchases_summary' => false,
        'sales_summary' => false,
        'audits' => false,

        // Extra configuration
        'merchant_settings' => false,
        'permission_modules' => false,
        'notification_templates' => false,
        'invoice_dynamic_fields' => false,
    ],

];
