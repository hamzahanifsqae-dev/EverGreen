<?php

namespace Database\Seeders\ZGN\PremiumLubricantsOils;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PremiumLubricantsOilsBrandModelsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $brandModels = [
            'Castrol' => [
                'Castrol GTX',
                'Castrol Magnatec',
                'Castrol Edge',
            ],
            'Mobil' => [
                'Mobil 1',
                'Mobil Super',
                'Mobil Delvac',
            ],
            'Shell' => [
                'Shell Helix',
                'Shell Rotella',
            ],
            'Total' => [
                'Total Quartz',
                'Total Rubia',
            ],
            'Valvoline' => [
                'Valvoline All-Climate',
                'Valvoline MaxLife',
            ],
            'Pennzoil' => [
                'Pennzoil Platinum',
                'Pennzoil Ultra',
            ],
            'Quaker State' => [
                'Quaker State Advanced Durability',
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
