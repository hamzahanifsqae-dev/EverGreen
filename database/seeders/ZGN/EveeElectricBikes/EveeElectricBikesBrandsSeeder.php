<?php

namespace Database\Seeders\ZGN\EveeElectricBikes;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EveeElectricBikesBrandsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $electricBikesCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Electric Bikes')
            ->whereNull('parent_id')
            ->first();

        if (! $electricBikesCategory) {
            return;
        }

        $brands = [
            'Evee',
            'Bosch',
            'Shimano',
            'Bafang',
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
                    'category_id' => $electricBikesCategory->id,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );
        }
    }
}
