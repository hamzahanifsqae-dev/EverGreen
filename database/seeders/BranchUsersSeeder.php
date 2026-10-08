<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BranchUsersSeeder extends Seeder
{
    public function run(): void
    {
        $primaryMerchant = Merchant::where('email', 'info@evergreen.com')->first();
        $primaryMerchantBranches = Branch::where('merchant_id', $primaryMerchant->id)->get();

        $halaynoor = Merchant::where('email', 'info@halaynoor.com')->first();
        $halaynoorBranches = Branch::where('merchant_id', $halaynoor->id)->get();

        if ($primaryMerchant) {
            foreach ($primaryMerchantBranches as $index => $branch) {
                $this->createUser(
                    merchant: $primaryMerchant,
                    name: $branch->name.' Staff',
                    email: "branch{$index}@flowdesk.com",
                    roleName: 'Data Entry',
                    branchId: $branch->id
                );
            }
        }

        if ($halaynoor) {
            foreach ($halaynoorBranches as $index => $branch) {
                $this->createUser(
                    merchant: $primaryMerchant,
                    name: $branch->name.' Staff',
                    email: "branch{$index}@halaynoor.com",
                    roleName: 'Data Entry',
                    branchId: $branch->id
                );
            }
        }
    }

    private function createUser(
        Merchant $merchant,
        string $name,
        string $email,
        string $roleName,
        string $branchId
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

        BranchUser::firstOrCreate(
            [
                'user_id' => $user->id,
                'branch_id' => $branchId,
            ],
            [
                'id' => Str::uuid(),
            ]
        );
    }
}
