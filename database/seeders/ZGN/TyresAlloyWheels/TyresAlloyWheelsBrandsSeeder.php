<?php

namespace Database\Seeders\ZGN\TyresAlloyWheels;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TyresAlloyWheelsBrandsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $tyresWheelsCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Tyres & Alloy Wheels')
            ->whereNull('parent_id')
            ->first();

        if (! $tyresWheelsCategory) {
            return;
        }

        $brands = [
            'Michelin',
            'Bridgestone',
            'Continental',
            'Goodyear',
            'Pirelli',
            'Dunlop',
            'Yokohama',
            'MRF',
            'CEAT',
            'Generic',
        ];

        foreach ($brands as $brandName) {
            $brand = Brand::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $brandName,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );

            BrandCategory::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'brand_id' => $brand->id,
                    'category_id' => $tyresWheelsCategory->id,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );
        }
    }
}
