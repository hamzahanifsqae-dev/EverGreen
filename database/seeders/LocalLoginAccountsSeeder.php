<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ensures known local login accounts exist with a shared dev password.
 * Run: php artisan db:seed --class=LocalLoginAccountsSeeder
 */
class LocalLoginAccountsSeeder extends Seeder
{
    private const PASSWORD = 'DD@2025@DD';

    public function run(): void
    {
        $merchant = Merchant::query()->where('email', 'info@evergreen.com')->first();

        if (! $merchant) {
            $this->command?->error('Merchant info@evergreen.com not found. Run MerchantsSeeder first.');

            return;
        }

        $merchant->forceFill([
            'password' => self::PASSWORD,
            'status' => Merchant::STATUS_VERIFIED,
            'is_active' => true,
        ])->save();

        $staffAccounts = [
            ['name' => 'Flowdesk Admin', 'email' => 'admin@flowdesk.com', 'role' => 'Admin'],
            ['name' => 'Flowdesk Supervisor', 'email' => 'supervisor@flowdesk.com', 'role' => 'Supervisor'],
            ['name' => 'Junaid', 'email' => 'junaid@flowdesk.com', 'role' => 'Admin'],
            ['name' => 'Shahrukh', 'email' => 'shahrukh@flowdesk.com', 'role' => 'Admin'],
            ['name' => 'Talha', 'email' => 'talha@flowdesk.com', 'role' => 'Admin'],
        ];

        foreach ($staffAccounts as $account) {
            $this->ensureStaffUser($merchant, $account['name'], $account['email'], $account['role']);
        }

        $this->command?->info('');
        $this->command?->info('Local login accounts ready:');
        $this->command?->info('  Merchant → http://127.0.0.1:8000/merchant/login');
        $this->command?->info('    Email: info@evergreen.com');
        $this->command?->info('    Password: '.self::PASSWORD);
        $this->command?->info('');
        $this->command?->info('  Staff → http://127.0.0.1:8000/staff/login');
        $this->command?->info('    Email: admin@flowdesk.com (or junaid@flowdesk.com)');
        $this->command?->info('    Password: '.self::PASSWORD);
    }

    private function ensureStaffUser(Merchant $merchant, string $name, string $email, string $roleName): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'merchant_id' => $merchant->id,
                'password' => self::PASSWORD,
                'status' => User::STATUS_VERIFIED,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->forceFill([
            'name' => $name,
            'merchant_id' => $merchant->id,
            'password' => self::PASSWORD,
            'status' => User::STATUS_VERIFIED,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'merchant')
            ->first();

        if ($role) {
            $user->assignRole($role);
        }
    }
}
