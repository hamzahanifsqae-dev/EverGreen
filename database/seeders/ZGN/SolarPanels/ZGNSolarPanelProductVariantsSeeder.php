<?php

namespace Database\Seeders\ZGN\SolarPanels;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNSolarPanelProductVariantsSeeder extends Seeder
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

        $sku = "{$merchantSlug}-solar-panel";
        $product = Product::where('sku', $sku)->first();

        $variants = [
            ['brand' => 'Longi', 'name' => 'Longi 540W Mono', 'sku' => 'LNG-540-MONO'],
            ['brand' => 'Longi', 'name' => 'Longi 550W Mono', 'sku' => 'LNG-550-MONO'],
            ['brand' => 'JA Solar', 'name' => 'JA Solar 550W Mono', 'sku' => 'JA-550-MONO'],
            ['brand' => 'Jinko Solar', 'name' => 'Jinko 560W Bifacial', 'sku' => 'JNK-560-BIF'],
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
