<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        $pakistan = Country::where('code', 'PK')->first();
        $karachi = City::where('name', 'Karachi')->first();

        if (! $pakistan || ! $karachi) {
            return;
        }

        $primaryMerchant = Merchant::where('email', 'info@evergreen.com')->first();
        $halaynoor = Merchant::where('email', 'info@halaynoor.com')->first();

        if ($primaryMerchant) {
            $this->createBranches(
                $primaryMerchant->id,
                $pakistan->id,
                $karachi->id,
                [
                    'Solar Systems' => 3,
                    'Evee Electric Bikes' => 1,
                    'Tyres & Alloy Wheels' => 2,
                    'Premium Lubricants & Oils' => 2,
                ]
            );
        }

        if ($halaynoor) {
            $this->createBranches(
                $halaynoor->id,
                $pakistan->id,
                $karachi->id,
                [
                    'Halaynoor' => 1,
                ]
            );
        }
    }

    public function createBranches(
        string $merchantId,
        string $countryId,
        string $cityId,
        array $branchesMap = []
    ): void {
        foreach ($branchesMap as $businessName => $count) {
            $business = Business::where('merchant_id', $merchantId)->where('name', $businessName)->first();
            if (! $business) {
                continue;
            }

            for ($i = 1; $i <= $count; $i++) {
                Branch::firstOrCreate(
                    [
                        'business_id' => $business->id,
                        'name' => $businessName." Branch {$i}",
                    ],
                    [
                        'id' => Str::uuid(),
                        'merchant_id' => $merchantId,
                        'address' => "City {$i}, Pakistan",
                        'phone' => '0300'.rand(1000000, 9999999),
                        'status' => 'active',
                        //                        'country_id' => $countryId,
                        //                        'city_id' => $cityId,
                        'postal_code' => '75500',
                    ]
                );
            }
        }
    }
}
