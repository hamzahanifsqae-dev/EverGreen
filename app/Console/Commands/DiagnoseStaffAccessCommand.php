<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic: prints the current role/permissions state for the
 * EverGreen staff login, with no side effects. Safe to run any number of
 * times via Railway's dashboard Shell.
 *
 * Run with: php artisan app:diagnose-staff-access
 */
class DiagnoseStaffAccessCommand extends Command
{
    protected $signature = 'app:diagnose-staff-access {--email=staff@evergreen.com}';

    protected $description = 'Print the current role and permission state for the EverGreen staff login';

    public function handle(): int
    {
        $email = (string) $this->option('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("User {$email} not found.");

            return self::FAILURE;
        }

        $this->info("User: {$email}");
        $this->line('Roles: '.($user->getRoleNames()->implode(', ') ?: 'none'));

        foreach ($user->roles as $role) {
            $this->line("  - {$role->name} ({$role->permissions->count()} permissions)");
        }

        $this->newLine();
        $this->line('Effective permissions ('.$user->getAllPermissions()->count().'):');
        $this->line('  '.$user->getAllPermissions()->pluck('name')->sort()->implode(', '));

        $this->newLine();
        $checks = [
            'roles_permissions.view',
            'merchants.view',
            'users.view',
            'branches.view',
            'businesses.view',
            'cold_storage.view',
        ];

        foreach ($checks as $permission) {
            $has = $user->hasPermissionTo($permission, 'merchant') ? 'YES' : 'no';
            $this->line(str_pad($permission, 25).' => '.$has);
        }

        $this->newLine();
        $admin = Role::query()->where('name', 'Admin')->where('guard_name', 'merchant')->first();

        if ($admin) {
            $this->line("Admin role currently has {$admin->permissions->count()} permissions.");
        }

        return self::SUCCESS;
    }
}
