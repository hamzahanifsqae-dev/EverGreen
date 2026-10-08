<?php

namespace Database\Seeders\ZGN\TyresAlloyWheels;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TyresAlloyWheelsBrandModelsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $brandModels = [
            'Michelin' => [
                'Michelin Energy XM2',
                'Michelin Primacy 4',
                'Michelin Pilot Sport',
            ],
            'Bridgestone' => [
                'Bridgestone Turanza',
                'Bridgestone Potenza',
                'Bridgestone Ecopia',
            ],
            'Continental' => [
                'Continental ContiPremiumContact',
                'Continental ContiSportContact',
            ],
            'Goodyear' => [
                'Goodyear Assurance',
                'Goodyear EfficientGrip',
            ],
            'Pirelli' => [
                'Pirelli P Zero',
                'Pirelli Cinturato',
            ],
            'Dunlop' => [
                'Dunlop SP Sport',
                'Dunlop Formula D',
            ],
            'Yokohama' => [
                'Yokohama Advan',
                'Yokohama BluEarth',
            ],
            'MRF' => [
                'MRF ZVTS',
                'MRF ZLX',
            ],
            'CEAT' => [
                'CEAT SecuraDrive',
                'CEAT Milaze',
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
