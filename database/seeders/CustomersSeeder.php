<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomersSeeder extends Seeder
{
    public function run(): void
    {
        $pakistan = Country::where('code', 'PK')->first();
        $karachi = City::where('name', 'Karachi')->first();

        if (! $pakistan || ! $karachi) {
            return;
        }

        $merchants = Merchant::whereIn('email', [
            'info@evergreen.com',
            'info@halaynoor.com',
        ])->get();

        foreach ($merchants as $merchant) {
            $this->createCustomers(
                $merchant->id,
                $pakistan->id,
                $karachi->id
            );
        }
    }

    private function createCustomers(
        string $merchantId,
        string $countryId,
        string $cityId
    ): void {
        for ($i = 1; $i <= 8; $i++) {
            Customer::firstOrCreate(
                [
                    'email' => "customer{$i}-{$merchantId}@example.com",
                ],
                [
                    'id' => Str::uuid(),
                    'merchant_id' => $merchantId,
                    'name' => "Customer {$i}",
                    'phone' => '03'.rand(100000000, 399999999),
                    'country_id' => $countryId,
                    'city_id' => $cityId,
                    'postal_code' => '75500',
                    'address' => "Street {$i}, Karachi, Pakistan",
                    'reference' => 'Walk-in',
                ]
            );
        }
    }
}
