<?php

namespace Database\Seeders\ZGN\Batteries;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNBatteryProductsOptionsSeeder extends Seeder
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
        if (! $product) {
            return;
        }

        $options = [
            ['name' => 'chemistry', 'display_name' => 'Chemistry'],
            ['name' => 'voltage', 'display_name' => 'Voltage'],
            ['name' => 'capacity', 'display_name' => 'Capacity'],
        ];

        foreach ($options as $opt) {
            ProductOption::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'name' => $opt['name'],
                ],
                [
                    'id' => Str::uuid(),
                    'display_name' => $opt['display_name'],
                ]
            );
        }
    }
}
