<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds only what is required to log in and use the app with empty business data.
 * Run: php artisan migrate:fresh --seed --seeder=CleanStartSeeder
 */
class CleanStartSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            CountriesSeeder::class,
            CitiesSeeder::class,
            MerchantsSeeder::class,
            PermissionsModulesSeeder::class,
            MerchantPermissionModulesSeeder::class,
            CreditPaymentReminderNotificationTemplateSeeder::class,
        ]);

        $this->command?->info('');
        $this->command?->info('Clean start complete. Database is empty except login essentials.');
        $this->command?->info('Merchant login → http://127.0.0.1:8000/merchant');
        $this->command?->info('Email: info@evergreen.com');
        $this->command?->info('Password: Evergreen@123');
        $this->command?->info('');
        $this->command?->info('Add your own: Business → Branch → Products → Purchases → Sales');
    }
}
