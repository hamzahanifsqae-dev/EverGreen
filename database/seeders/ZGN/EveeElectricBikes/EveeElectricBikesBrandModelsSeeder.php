<?php

namespace Database\Seeders\ZGN\EveeElectricBikes;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EveeElectricBikesBrandModelsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $brandModels = [
            'Evee' => [
                'Evee City Pro',
                'Evee Mountain Elite',
                'Evee Commuter Plus',
                'Evee Sport',
            ],
            'Bosch' => [
                'Bosch Active Line',
                'Bosch Performance Line',
                'Bosch Cargo Line',
            ],
            'Shimano' => [
                'Shimano STEPS E6100',
                'Shimano STEPS E8000',
            ],
            'Bafang' => [
                'Bafang BBS02',
                'Bafang BBSHD',
                'Bafang M400',
            ],
            'Generic' => [
                'Generic Standard',
            ],
        ];

        foreach ($brandModels as $brandName => $models) {
            $brand = Brand::where('merchant_id', $merchant->id)
                ->where('name', $brandName)
                ->first();

            if (! $brand) {
                continue;
            }

            foreach ($models as $modelName) {
                BrandModel::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'brand_id' => $brand->id,
                        'name' => $modelName,
                    ],
                    [
                        'id' => Str::uuid(),
                    ]
                );
            }
        }
    }
}
