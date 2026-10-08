<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StaffsSeeder extends Seeder
{
    public function run(): void
    {
        $primaryMerchant = Merchant::where('email', 'info@evergreen.com')->first();
        $halaynoor = Merchant::where('email', 'info@halaynoor.com')->first();

        if ($primaryMerchant) {
            $this->createUser(
                merchant: $primaryMerchant,
                name: 'Flowdesk Admin',
                email: 'admin@flowdesk.com',
                roleName: 'Admin'
            );

            $this->createUser(
                merchant: $primaryMerchant,
                name: 'Flowdesk Supervisor',
                email: 'supervisor@flowdesk.com',
                roleName: 'Supervisor'
            );
        }

        if ($halaynoor) {
            $this->createUser(
                merchant: $halaynoor,
                name: 'Halaynoor Admin',
                email: 'admin@halaynoor.com',
                roleName: 'Admin'
            );

            $this->createUser(
                merchant: $halaynoor,
                name: 'Halaynoor Support',
                email: 'support@halaynoor.com',
                roleName: 'Support Admin'
            );
        }
    }

    private function createUser(
        Merchant $merchant,
        string $name,
        string $email,
        string $roleName
    ): void {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'id' => Str::uuid(),
                'name' => $name,
                'merchant_id' => $merchant->id,
                'password' => 'DD@2025@DD',
                'status' => 'verified',
                'is_active' => true,
            ]
        );

        $role = Role::where('name', $roleName)->where('guard_name', 'merchant')->first();
        if ($role) {
            $user->assignRole($role);
        }
    }
}
