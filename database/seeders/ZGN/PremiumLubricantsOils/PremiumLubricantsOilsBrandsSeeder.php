<?php

namespace Database\Seeders\ZGN\PremiumLubricantsOils;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PremiumLubricantsOilsBrandsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $lubricantsOilsCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Premium Lubricants & Oils')
            ->whereNull('parent_id')
            ->first();

        if (! $lubricantsOilsCategory) {
            return;
        }

        $brands = [
            'Castrol',
            'Mobil',
            'Shell',
            'Total',
            'Valvoline',
            'Pennzoil',
            'Quaker State',
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
                    'category_id' => $lubricantsOilsCategory->id,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );
        }
    }
}
