<?php

namespace Database\Seeders\ZGN\SolarInverters;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNInverterProductVariantsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $merchantSlug = collect(explode(' ', $merchant->name))
            ->map(fn ($word) => Str::lower(Str::substr($word, 0, 1)))
            ->implode('');

        $sku = "{$merchantSlug}-solar-inverter";
        $product = Product::where('sku', $sku)->first();

        $variants = [
            ['name' => 'Huawei 5kW Hybrid', 'sku' => 'HUA-5K-HYB'],
            ['name' => 'Huawei 10kW Hybrid', 'sku' => 'HUA-10K-HYB'],
            ['name' => 'Growatt 5kW On-Grid', 'sku' => 'GRT-5K-ONG'],
            ['name' => 'Growatt 20kW On-Grid', 'sku' => 'GRT-20K-ONG'],
            ['name' => 'Inverex 5kW Hybrid', 'sku' => 'INV-5K-HYB'],
        ];

        foreach ($variants as $v) {
            ProductVariant::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'sku' => $v['sku'],
                ],
                [
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'name' => $v['name'],
                ]
            );
        }
    }
}
