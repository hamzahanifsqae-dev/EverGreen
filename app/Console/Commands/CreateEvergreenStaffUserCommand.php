<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates (or resets) the staff login used alongside the EverGreen merchant
 * account. Safe to run more than once — it just updates the same record.
 *
 * Run with: php artisan app:create-evergreen-staff-user
 */
class CreateEvergreenStaffUserCommand extends Command
{
    protected $signature = 'app:create-evergreen-staff-user
                            {--email=staff@evergreen.com}
                            {--password=Evergreen@123}
                            {--name=EverGreen Staff}
                            {--role=Staff}
                            {--merchant-email=info@evergreen.com}';

    protected $description = 'Create or update the staff login for the EverGreen merchant';

    public function handle(): int
    {
        $merchant = Merchant::query()->where('email', $this->option('merchant-email'))->first();

        if (! $merchant) {
            $this->error('Merchant '.$this->option('merchant-email').' not found.');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'id' => (string) Str::uuid(),
                'name' => (string) $this->option('name'),
                'merchant_id' => $merchant->id,
                'password' => (string) $this->option('password'),
                'status' => 'verified',
                'is_active' => true,
            ],
        );

        $role = Role::query()->where('name', $this->option('role'))->where('guard_name', 'merchant')->first();

        if ($role) {
            $user->syncRoles([$role]);
        } else {
            $this->warn('Role "'.$this->option('role').'" not found — user created without a role.');
        }

        $branch = Branch::query()->where('merchant_id', $merchant->id)->first();

        if ($branch) {
            BranchUser::query()->firstOrCreate(
                ['user_id' => $user->id, 'branch_id' => $branch->id],
                ['id' => (string) Str::uuid()],
            );
        }

        $this->info('Staff login ready: '.$email);
        $this->line('Role: '.($role?->name ?? 'none').' | Branch: '.($branch?->name ?? 'none'));

        return self::SUCCESS;
    }
}
