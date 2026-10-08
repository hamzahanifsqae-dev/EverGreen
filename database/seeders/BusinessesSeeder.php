<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessesSeeder extends Seeder
{
    public function run(): void
    {
        $primaryMerchant = Merchant::where('email', 'info@evergreen.com')->first();
        $halaynoor = Merchant::where('email', 'info@halaynoor.com')->first();

        $pakistan = Country::where('code', 'PK')->first();
        $karachi = City::where('name', 'Karachi')->first();
        if (! $pakistan || ! $karachi) {
            return;
        }

        if ($primaryMerchant) {
            $this->createBusiness(
                $primaryMerchant->id,
                $pakistan->id,
                $karachi->id,
                [
                    'Solar Systems',
                    'Evee Electric Bikes',
                    'Tyres & Alloy Wheels',
                    'Premium Lubricants & Oils',
                ]
            );
        }

        if ($halaynoor) {
            $this->createBusiness(
                $halaynoor->id,
                $pakistan->id,
                $karachi->id,
                [
                    'Halaynoor',
                ]
            );
        }
    }

    private function createBusiness(
        string $merchantId,
        string $countryId,
        string $cityId,
        array $businesses = []
    ): void {
        foreach ($businesses as $name) {
            $business = Business::firstOrCreate(
                ['merchant_id' => $merchantId, 'name' => $name],
                [
                    'id' => Str::uuid(),
                    'description' => $name.' business',
                    'status' => true,
                    'postal_code' => '75500',
                ]
            );

            $business->countries()->syncWithoutDetaching([$countryId]);
            $business->cities()->syncWithoutDetaching([$cityId]);
        }
    }
}
