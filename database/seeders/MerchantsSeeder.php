<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Services\Demo\DemoMerchantAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MerchantsSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = [
            [
                'email' => 'info@evergreen.com',
                'legacy_emails' => ['info@flowdesk.com'],
                'name' => 'EverGreen Cold Storage',
                'website' => 'https://flowdesk.app/',
                'password' => 'Evergreen@123',
            ],
            [
                'email' => 'info@halaynoor.com',
                'legacy_emails' => [],
                'name' => 'Halaynoor',
                'website' => 'https://halaynoor.com/',
                'password' => 'DD@2025@DD',
            ],
        ];

        $demoMerchantAccess = app(DemoMerchantAccess::class);

        foreach ($merchants as $data) {
            $merchant = Merchant::query()
                ->where('email', $data['email'])
                ->when(
                    $data['legacy_emails'] !== [],
                    fn ($query) => $query->orWhereIn('email', $data['legacy_emails']),
                )
                ->first();

            if (! $merchant) {
                $merchant = new Merchant([
                    'id' => Str::uuid()->toString(),
                    'email' => $data['email'],
                    'phone' => null,
                    'address_line_1' => 'Pakistan',
                    'city' => 'Karachi',
                ]);
            }

            $merchant->forceFill([
                'email' => $data['email'],
                'name' => $data['name'],
                'website' => $data['website'],
                'status' => Merchant::STATUS_VERIFIED,
                'is_active' => true,
                'password' => $data['password'],
            ])->save();

            $demoMerchantAccess->grantFullAccess($merchant);
        }
    }
}
