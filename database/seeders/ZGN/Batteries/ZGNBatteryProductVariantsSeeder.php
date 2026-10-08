<?php

namespace Database\Seeders\ZGN\Batteries;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNBatteryProductVariantsSeeder extends Seeder
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

        $sku = "{$merchantSlug}-solar-battery";
        $product = Product::where('sku', $sku)->first();

        $variants = [
            ['name' => 'Phoenix 150Ah Lead Acid', 'sku' => 'PHX-150AH-LA'],
            ['name' => 'Phoenix 200Ah Lead Acid', 'sku' => 'PHX-200AH-LA'],
            ['name' => 'AGS 200Ah Lead Acid',     'sku' => 'AGS-200AH-LA'],
            ['name' => 'Pylontech US2000C',       'sku' => 'PYL-2.4KWH-LFP'],
            ['name' => 'Pylontech US3000C',       'sku' => 'PYL-3.5KWH-LFP'],
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
